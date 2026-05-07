<?php

namespace App\Http\Controllers;

use App\Models\Box;
use App\Models\BoxSession;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryDriver;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Services\DeliveryCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DeliveryManagementController extends Controller
{
    private const DEFAULT_STATUS = 'active';

    private const AVAILABLE_STATUSES = ['active', 'delivered', 'cancelled'];

    public function __construct(
        private readonly DeliveryCheckoutService $checkoutService
    ) {
    }

    public function index(Request $request)
    {
        if ($response = $this->denyIfUnauthorized($this->deliveryPermissions())) {
            return $response;
        }

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(self::AVAILABLE_STATUSES)],
            'delivery_driver_id' => ['nullable', 'integer', 'exists:delivery_drivers,id'],
        ]);

        $deliveries = Delivery::query()
            ->with(['customer', 'deliveryDriver', 'assignedUser', 'sale.invoice', 'sale.payments.paymentMethod'])
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
                'active' => Delivery::query()->where('status', self::DEFAULT_STATUS)->count(),
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
                'status' => self::DEFAULT_STATUS,
                'scheduled_at' => now(),
                'customer_payment_amount' => 0,
                'change_required' => 0,
            ]),
            'customers' => $this->customers(),
            'deliveryDrivers' => $this->deliveryDrivers(),
            'availableProducts' => $this->availableProducts(),
            'deliveryRows' => $this->deliveryRows(),
            'formAction' => route('deliveries.store'),
            'submitLabel' => 'Guardar domicilio',
        ]);
    }

    public function store(Request $request): RedirectResponse|Response
    {
        if ($response = $this->denyIfUnauthorized(['deliveries.create'])) {
            return $response;
        }

        $this->storeOrUpdateDelivery($request);

        return redirect()
            ->route('deliveries.index')
            ->with('success', 'Domicilio creado correctamente.');
    }

    public function showCheckout(Delivery $delivery)
    {
        if ($response = $this->denyIfUnauthorized(['deliveries.edit'])) {
            return $response;
        }

        $delivery->load([
            'customer',
            'deliveryDriver',
            'items.product',
            'sale.invoice',
            'sale.payments.paymentMethod',
        ]);

        if ($delivery->status === 'cancelled') {
            return redirect()
                ->route('deliveries.index')
                ->with('info', 'Este domicilio esta cancelado y no se puede cobrar.');
        }

        if ($delivery->sale) {
            return redirect()
                ->route('deliveries.index')
                ->with('info', 'Este domicilio ya fue cobrado y su documento ya esta disponible.');
        }

        return view('deliveries.checkout', [
            'delivery' => $delivery,
            'activeBox' => $this->activeBox(),
            'paymentMethods' => $this->paymentMethods(),
        ]);
    }

    public function processCheckout(Request $request, Delivery $delivery): RedirectResponse|Response
    {
        if ($response = $this->denyIfUnauthorized(['deliveries.edit'])) {
            return $response;
        }

        if ($delivery->sale_id) {
            return redirect()
                ->route('deliveries.index')
                ->with('info', 'Este domicilio ya fue cobrado.');
        }

        $validated = $request->validate([
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'amount_received' => ['required', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->checkoutService->checkout($delivery, $validated, (int) Auth::id());
        $sale = $result['sale'];

        session()->flash('success', 'Cobro registrado correctamente. La venta y el movimiento de caja quedaron guardados.');

        return response()->view('orders.print-bridge', [
            'title' => 'Preparando documento',
            'message' => 'Estamos abriendo el ticket y en unos segundos volveras al listado de domicilios.',
            'primaryActionLabel' => 'Abrir documento',
            'secondaryActionLabel' => 'Ir a domicilios',
            'redirectUrl' => route('deliveries.index'),
            'printUrl' => route('pos.sales.print', $sale),
        ]);
    }

    public function edit(Delivery $delivery)
    {
        if ($response = $this->denyIfUnauthorized(['deliveries.edit'])) {
            return $response;
        }

        if ($delivery->sale_id) {
            return redirect()
                ->route('deliveries.index')
                ->with('info', 'Este domicilio ya fue cobrado y no se puede editar.');
        }

        $delivery->loadMissing('items');

        return view('deliveries.form', [
            'pageTitle' => 'Editar domicilio',
            'delivery' => $delivery,
            'customers' => $this->customers(),
            'deliveryDrivers' => $this->deliveryDrivers($delivery->delivery_driver_id),
            'availableProducts' => $this->availableProducts(),
            'deliveryRows' => $this->deliveryRows($delivery),
            'formAction' => route('deliveries.update', $delivery),
            'submitLabel' => 'Actualizar domicilio',
        ]);
    }

    public function update(Request $request, Delivery $delivery): RedirectResponse|Response
    {
        if ($response = $this->denyIfUnauthorized(['deliveries.edit'])) {
            return $response;
        }

        if ($delivery->sale_id) {
            return redirect()
                ->route('deliveries.index')
                ->with('info', 'Este domicilio ya fue cobrado y no se puede editar.');
        }

        $this->storeOrUpdateDelivery($request, $delivery);

        return redirect()
            ->route('deliveries.index')
            ->with('success', 'Domicilio actualizado correctamente.');
    }

    public function destroy(Delivery $delivery): RedirectResponse|Response
    {
        if ($response = $this->denyIfUnauthorized(['deliveries.delete'])) {
            return $response;
        }

        if ($delivery->sale_id) {
            return redirect()
                ->route('deliveries.index')
                ->with('warning', 'No se puede eliminar un domicilio que ya fue cobrado.');
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
        return $request->validate([
            'delivery_number' => ['nullable', 'string', 'max:255', Rule::unique('deliveries', 'delivery_number')->ignore($delivery?->id)],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'delivery_address' => ['required', 'string'],
            'reference' => ['nullable', 'string', 'max:255'],
            'delivery_fee_is_free' => ['nullable', 'boolean'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'customer_payment_amount' => ['required', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(self::AVAILABLE_STATUSES)],
            'delivery_driver_id' => ['nullable', 'exists:delivery_drivers,id'],
            'scheduled_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
        ]);
    }

    private function storeOrUpdateDelivery(Request $request, ?Delivery $delivery = null): Delivery
    {
        $validated = $this->validateDeliveryData($request, $delivery);

        $rows = $this->itemRowsFromRequest($request);

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Debes agregar al menos un producto al domicilio.',
            ]);
        }

        $customer = ! empty($validated['customer_id'])
            ? Customer::query()->whereKey($validated['customer_id'])->where('is_active', true)->first()
            : null;

        if (! empty($validated['customer_id']) && ! $customer) {
            throw ValidationException::withMessages([
                'customer_id' => 'Selecciona un cliente activo o usa la opcion sin vincular.',
            ]);
        }

        $products = Product::query()
            ->visibleInMenu()
            ->whereIn('id', $rows->pluck('product_id'))
            ->get()
            ->keyBy('id');

        $items = $rows->map(function (array $row) use ($products): array {
            /** @var Product|null $product */
            $product = $products->get($row['product_id']);

            if (! $product) {
                throw ValidationException::withMessages([
                    'items' => 'Uno de los productos seleccionados ya no esta disponible para domicilios.',
                ]);
            }

            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'unit_price' => round((float) $product->price, 2),
                'quantity' => $row['quantity'],
                'subtotal' => round((float) $product->price * $row['quantity'], 2),
            ];
        });

        $deliveryFeeIsFree = $request->boolean('delivery_fee_is_free');
        $deliveryFee = $deliveryFeeIsFree
            ? 0.0
            : round((float) ($validated['delivery_fee'] ?? 0), 2);

        if (! $deliveryFeeIsFree && $deliveryFee <= 0) {
            throw ValidationException::withMessages([
                'delivery_fee' => 'Ingresa el costo del domicilio o marca que es gratis.',
            ]);
        }

        $orderTotal = round((float) $items->sum('subtotal'), 2);
        $totalCharge = round($orderTotal + $deliveryFee, 2);
        $customerPaymentAmount = round((float) $validated['customer_payment_amount'], 2);

        if ($customerPaymentAmount < $totalCharge) {
            throw ValidationException::withMessages([
                'customer_payment_amount' => 'El valor con el que paga el cliente no puede ser menor al total del domicilio.',
            ]);
        }

        $savedDelivery = $delivery;

        DB::transaction(function () use (
            $validated,
            $delivery,
            $customer,
            $items,
            $orderTotal,
            $deliveryFee,
            $totalCharge,
            $customerPaymentAmount,
            &$savedDelivery
        ): void {
            $payload = $this->buildPayload(
                $validated,
                $orderTotal,
                $deliveryFee,
                $totalCharge,
                $customerPaymentAmount,
                $delivery
            );

            $payload['customer_id'] = $customer?->id;

            if ($delivery) {
                $delivery->update($payload);
                $savedDelivery = $delivery;
            } else {
                $savedDelivery = Delivery::create($payload);
            }

            $savedDelivery->items()->delete();

            foreach ($items as $item) {
                $savedDelivery->items()->create($item);
            }
        });

        return $savedDelivery;
    }

    private function buildPayload(
        array $validated,
        float $orderTotal,
        float $deliveryFee,
        float $totalCharge,
        float $customerPaymentAmount,
        ?Delivery $delivery = null
    ): array {
        $resolvedStatus = $this->resolveStatus($validated, $delivery);
        $deliveredAt = $resolvedStatus === 'delivered'
            ? ($delivery?->delivered_at ?? now())
            : null;

        return [
            'delivery_number' => $this->resolveDeliveryNumber($validated, $delivery),
            'customer_id' => $validated['customer_id'] ?? null,
            'delivery_driver_id' => $validated['delivery_driver_id'] ?? null,
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'] ?? null,
            'delivery_address' => $validated['delivery_address'],
            'reference' => $validated['reference'] ?? null,
            'order_total' => $orderTotal,
            'delivery_fee' => $deliveryFee,
            'total_charge' => $totalCharge,
            'customer_payment_amount' => $customerPaymentAmount,
            'change_required' => max(round($customerPaymentAmount - $totalCharge, 2), 0),
            'status' => $resolvedStatus,
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'delivered_at' => $deliveredAt,
            'notes' => $validated['notes'] ?? null,
        ];
    }

    private function resolveDeliveryNumber(array $validated, ?Delivery $delivery = null): string
    {
        $deliveryNumber = trim((string) ($validated['delivery_number'] ?? ''));

        if ($deliveryNumber !== '') {
            return $deliveryNumber;
        }

        return $delivery?->delivery_number ?: Delivery::generateDeliveryNumber();
    }

    private function resolveStatus(array $validated, ?Delivery $delivery = null): string
    {
        $status = $validated['status'] ?? null;

        if (is_string($status) && in_array($status, self::AVAILABLE_STATUSES, true)) {
            return $status;
        }

        return $delivery?->status ?: self::DEFAULT_STATUS;
    }

    private function customers()
    {
        return Customer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);
    }

    private function availableProducts(): Collection
    {
        return Product::query()
            ->with('menuCategory:id,name,description,sort_order,is_active')
            ->visibleInMenu()
            ->orderedForMenu()
            ->get([
                'products.id',
                'products.name',
                'products.description',
                'products.price',
                'products.category_id',
                'products.product_type',
                'products.sort_order',
                'products.image_path',
            ]);
    }

    private function deliveryRows(?Delivery $delivery = null): array
    {
        $oldRows = old('items');

        if (is_array($oldRows) && count($oldRows) > 0) {
            return array_values($oldRows);
        }

        if (! $delivery || ! $delivery->exists) {
            return [];
        }

        $delivery->loadMissing('items');

        return $delivery->items
            ->map(fn ($item) => [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
            ])
            ->values()
            ->all();
    }

    private function itemRowsFromRequest(Request $request): Collection
    {
        return collect($request->input('items', []))
            ->map(function (array $row): array {
                return [
                    'product_id' => isset($row['product_id']) ? (int) $row['product_id'] : null,
                    'quantity' => isset($row['quantity']) ? (int) $row['quantity'] : null,
                ];
            })
            ->filter(fn (array $row) => ! empty($row['product_id']) && ! empty($row['quantity']))
            ->groupBy('product_id')
            ->map(function (Collection $group, int|string $productId): array {
                return [
                    'product_id' => (int) $productId,
                    'quantity' => $group->sum('quantity'),
                ];
            })
            ->values();
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

    private function activeBox(): ?Box
    {
        $session = BoxSession::query()
            ->with('box')
            ->where('status', 'open')
            ->where('user_id', Auth::id())
            ->latest('opened_at')
            ->first();

        return $session?->box
            ?? Box::query()
                ->where('status', 'open')
                ->whereHas('activeSession')
                ->orderByDesc('opened_at')
                ->first();
    }

    private function paymentMethods()
    {
        return PaymentMethod::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    private function deliveryPermissions(): array
    {
        return ['deliveries.view', 'deliveries.create', 'deliveries.edit', 'deliveries.delete'];
    }

    private function denyIfUnauthorized(array $permissions): ?Response
    {
        $user = auth()->user();

        if ($user && ($user->hasRole('Admin') || $user->hasRole('Cajero') || $user->hasAnyPermission($permissions))) {
            return null;
        }

        return response()->view('errors.403', [], 403);
    }
}
