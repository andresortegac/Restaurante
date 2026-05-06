<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryDriver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DeliveryManagementController extends Controller
{
    public function index(Request $request)
    {
        if ($response = $this->denyIfUnauthorized($this->deliveryPermissions())) {
            return $response;
        }

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:pending,assigned,in_transit,delivered,cancelled'],
            'delivery_driver_id' => ['nullable', 'integer', 'exists:delivery_drivers,id'],
        ]);

        $deliveries = Delivery::query()
            ->with(['customer', 'deliveryDriver', 'assignedUser'])
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($nestedQuery) use ($search) {
                    $nestedQuery
                        ->where('delivery_number', 'like', '%' . $search . '%')
                        ->orWhere('customer_name', 'like', '%' . $search . '%')
                        ->orWhere('customer_phone', 'like', '%' . $search . '%')
                        ->orWhere('delivery_address', 'like', '%' . $search . '%')
                        ->orWhereHas('deliveryDriver', fn ($driverQuery) => $driverQuery->where('name', 'like', '%' . $search . '%'))
                        ->orWhereHas('assignedUser', fn ($userQuery) => $userQuery->where('name', 'like', '%' . $search . '%'));
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['delivery_driver_id'] ?? null, fn ($query, int $driverId) => $query->where('delivery_driver_id', $driverId))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('deliveries.index', [
            'deliveries' => $deliveries,
            'filters' => $filters,
            'deliveryDrivers' => $this->deliveryDrivers($filters['delivery_driver_id'] ?? null),
            'summary' => [
                'total' => Delivery::query()->count(),
                'pending' => Delivery::query()->whereIn('status', ['pending', 'assigned', 'in_transit'])->count(),
                'delivered' => Delivery::query()->where('status', 'delivered')->count(),
                'cancelled' => Delivery::query()->where('status', 'cancelled')->count(),
            ],
        ]);
    }

    public function create()
    {
        if ($response = $this->denyIfUnauthorized(['deliveries.create'])) {
            return $response;
        }

        return view('deliveries.form', [
            'pageTitle' => 'Nuevo domicilio',
            'delivery' => new Delivery([
                'status' => 'pending',
                'delivery_number' => Delivery::generateDeliveryNumber(),
                'customer_payment_amount' => 0,
                'change_required' => 0,
            ]),
            'customers' => $this->customers(),
            'deliveryDrivers' => $this->deliveryDrivers(),
            'formAction' => route('deliveries.store'),
            'submitLabel' => 'Guardar domicilio',
        ]);
    }

    public function store(Request $request): RedirectResponse|Response
    {
        if ($response = $this->denyIfUnauthorized(['deliveries.create'])) {
            return $response;
        }

        $validated = $this->validateDeliveryData($request);

        Delivery::create($this->buildPayload($validated));

        return redirect()
            ->route('deliveries.index')
            ->with('success', 'Domicilio creado correctamente.');
    }

    public function edit(Delivery $delivery)
    {
        if ($response = $this->denyIfUnauthorized(['deliveries.edit'])) {
            return $response;
        }

        return view('deliveries.form', [
            'pageTitle' => 'Editar domicilio',
            'delivery' => $delivery,
            'customers' => $this->customers(),
            'deliveryDrivers' => $this->deliveryDrivers($delivery->delivery_driver_id),
            'formAction' => route('deliveries.update', $delivery),
            'submitLabel' => 'Actualizar domicilio',
        ]);
    }

    public function update(Request $request, Delivery $delivery): RedirectResponse|Response
    {
        if ($response = $this->denyIfUnauthorized(['deliveries.edit'])) {
            return $response;
        }

        $validated = $this->validateDeliveryData($request, $delivery);

        $delivery->update($this->buildPayload($validated, $delivery));

        return redirect()
            ->route('deliveries.index')
            ->with('success', 'Domicilio actualizado correctamente.');
    }

    public function destroy(Delivery $delivery): RedirectResponse|Response
    {
        if ($response = $this->denyIfUnauthorized(['deliveries.delete'])) {
            return $response;
        }

        $delivery->delete();

        return redirect()
            ->route('deliveries.index')
            ->with('success', 'Domicilio eliminado correctamente.');
    }

    public function complete(Request $request, Delivery $delivery): RedirectResponse|Response
    {
        if ($response = $this->denyIfUnauthorized(['deliveries.edit'])) {
            return $response;
        }

        $request->validate([
            'delivery_proof_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $delivery->update([
            'status' => 'delivered',
            'delivered_at' => $delivery->delivered_at ?? now(),
            'delivery_proof_image_path' => $this->syncProofImage($request, $delivery->delivery_proof_image_path),
        ]);

        return redirect()
            ->route('deliveries.index')
            ->with('success', 'Entrega registrada correctamente.');
    }

    private function validateDeliveryData(Request $request, ?Delivery $delivery = null): array
    {
        $validated = $request->validate([
            'delivery_number' => ['required', 'string', 'max:255', Rule::unique('deliveries', 'delivery_number')->ignore($delivery?->id)],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'delivery_address' => ['required', 'string'],
            'reference' => ['nullable', 'string', 'max:255'],
            'order_total' => ['required', 'numeric', 'min:0'],
            'delivery_fee' => ['required', 'numeric', 'min:0'],
            'customer_payment_amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:pending,assigned,in_transit,delivered,cancelled'],
            'delivery_driver_id' => ['nullable', 'exists:delivery_drivers,id'],
            'scheduled_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $totalCharge = round((float) $validated['order_total'] + (float) $validated['delivery_fee'], 2);

        if ((float) $validated['customer_payment_amount'] < $totalCharge) {
            throw ValidationException::withMessages([
                'customer_payment_amount' => 'El valor con el que paga el cliente no puede ser menor al total del domicilio.',
            ]);
        }

        return $validated;
    }

    private function buildPayload(array $validated, ?Delivery $delivery = null): array
    {
        $deliveredAt = $validated['status'] === 'delivered'
            ? ($delivery?->delivered_at ?? now())
            : null;

        return [
            'delivery_number' => $validated['delivery_number'],
            'customer_id' => $validated['customer_id'] ?? null,
            'delivery_driver_id' => $validated['delivery_driver_id'] ?? null,
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'] ?? null,
            'delivery_address' => $validated['delivery_address'],
            'reference' => $validated['reference'] ?? null,
            'order_total' => $validated['order_total'],
            'delivery_fee' => $validated['delivery_fee'],
            'total_charge' => round((float) $validated['order_total'] + (float) $validated['delivery_fee'], 2),
            'customer_payment_amount' => $validated['customer_payment_amount'],
            'change_required' => max(round((float) $validated['customer_payment_amount'] - ((float) $validated['order_total'] + (float) $validated['delivery_fee']), 2), 0),
            'status' => $validated['status'],
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'delivered_at' => $deliveredAt,
            'notes' => $validated['notes'] ?? null,
        ];
    }

    private function customers()
    {
        return Customer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);
    }

    private function deliveryDrivers(?int $selectedId = null)
    {
        return DeliveryDriver::query()
            ->when(
                $selectedId,
                fn ($query, int $driverId) => $query->where(function ($nestedQuery) use ($driverId) {
                    $nestedQuery
                        ->where('is_active', true)
                        ->orWhereKey($driverId);
                }),
                fn ($query) => $query->where('is_active', true)
            )
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);
    }

    private function syncProofImage(Request $request, ?string $currentProofImagePath): ?string
    {
        if (! $request->hasFile('delivery_proof_image')) {
            return $currentProofImagePath;
        }

        if ($currentProofImagePath) {
            Storage::disk('public')->delete($currentProofImagePath);
        }

        return $request->file('delivery_proof_image')->store('delivery-proofs', 'public');
    }

    private function deliveryPermissions(): array
    {
        return ['deliveries.view', 'deliveries.create', 'deliveries.edit', 'deliveries.delete'];
    }

    private function denyIfUnauthorized(array $permissions): ?Response
    {
        $user = auth()->user();

        if ($user && ($user->hasRole('Admin') || $user->hasAnyPermission($permissions))) {
            return null;
        }

        return response()->view('errors.403', [], 403);
    }
}
