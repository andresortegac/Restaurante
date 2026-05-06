<?php

namespace App\Http\Controllers;

use App\Models\DeliveryDriver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DeliveryDriverManagementController extends Controller
{
    public function index(Request $request)
    {
        if ($response = $this->denyIfUnauthorized($this->driverPermissions())) {
            return $response;
        }

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $drivers = DeliveryDriver::query()
            ->withCount('deliveries')
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($nestedQuery) use ($search) {
                    $nestedQuery
                        ->where('name', 'like', '%' . $search . '%')
                        ->orWhere('document_number', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%')
                        ->orWhere('vehicle_plate', 'like', '%' . $search . '%');
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('is_active', $status === 'active'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('deliveries.drivers.index', [
            'drivers' => $drivers,
            'filters' => $filters,
            'summary' => [
                'total' => DeliveryDriver::query()->count(),
                'active' => DeliveryDriver::query()->where('is_active', true)->count(),
                'inactive' => DeliveryDriver::query()->where('is_active', false)->count(),
            ],
        ]);
    }

    public function create()
    {
        if ($response = $this->denyIfUnauthorized(['delivery_drivers.create'])) {
            return $response;
        }

        return view('deliveries.drivers.form', [
            'pageTitle' => 'Nuevo domiciliario',
            'driver' => new DeliveryDriver(['is_active' => true]),
            'formAction' => route('deliveries.drivers.store'),
            'submitLabel' => 'Guardar domiciliario',
            'vehicleTypeOptions' => DeliveryDriver::VEHICLE_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse|Response
    {
        if ($response = $this->denyIfUnauthorized(['delivery_drivers.create'])) {
            return $response;
        }

        $validated = $this->validateDriverData($request);
        $request->attributes->set('current_photo_path', null);

        DeliveryDriver::create($this->buildDriverPayload($validated, $request));

        return redirect()
            ->route('deliveries.drivers.index')
            ->with('success', 'Domiciliario creado correctamente.');
    }

    public function edit(DeliveryDriver $driver)
    {
        if ($response = $this->denyIfUnauthorized(['delivery_drivers.edit'])) {
            return $response;
        }

        return view('deliveries.drivers.form', [
            'pageTitle' => 'Editar domiciliario',
            'driver' => $driver,
            'formAction' => route('deliveries.drivers.update', $driver),
            'submitLabel' => 'Actualizar domiciliario',
            'vehicleTypeOptions' => DeliveryDriver::VEHICLE_TYPES,
        ]);
    }

    public function update(Request $request, DeliveryDriver $driver): RedirectResponse|Response
    {
        if ($response = $this->denyIfUnauthorized(['delivery_drivers.edit'])) {
            return $response;
        }

        $validated = $this->validateDriverData($request, $driver);
        $request->attributes->set('current_photo_path', $driver->photo_path);

        $driver->update($this->buildDriverPayload($validated, $request));

        return redirect()
            ->route('deliveries.drivers.index')
            ->with('success', 'Domiciliario actualizado correctamente.');
    }

    public function destroy(DeliveryDriver $driver): RedirectResponse|Response
    {
        if ($response = $this->denyIfUnauthorized(['delivery_drivers.delete'])) {
            return $response;
        }

        if ($driver->deliveries()->exists()) {
            $driver->update(['is_active' => false]);

            return redirect()
                ->route('deliveries.drivers.index')
                ->with('warning', 'El domiciliario ya tiene domicilios registrados. Se marco como inactivo para conservar el historico.');
        }

        if ($driver->photo_path) {
            Storage::disk('public')->delete($driver->photo_path);
        }

        $driver->delete();

        return redirect()
            ->route('deliveries.drivers.index')
            ->with('success', 'Domiciliario eliminado correctamente.');
    }

    private function validateDriverData(Request $request, ?DeliveryDriver $driver = null): array
    {
        $request->merge([
            'vehicle_type' => strtolower(trim((string) $request->input('vehicle_type'))),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:255', Rule::unique('delivery_drivers', 'document_number')->ignore($driver?->id)],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('delivery_drivers', 'email')->ignore($driver?->id)],
            'address' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['nullable', 'boolean'],
            'vehicle_type' => ['required', 'string', Rule::in(array_keys(DeliveryDriver::VEHICLE_TYPES))],
            'vehicle_plate' => [
                Rule::requiredIf(fn () => $request->input('vehicle_type') !== 'bicicleta'),
                'nullable',
                'string',
                'max:50',
            ],
            'vehicle_model' => [
                Rule::requiredIf(fn () => $request->input('vehicle_type') !== 'bicicleta'),
                'nullable',
                'string',
                'max:100',
            ],
            'vehicle_color' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }

    private function buildDriverPayload(array $validated, Request $request): array
    {
        $currentPhotoPath = $request->attributes->get('current_photo_path');
        $photoPath = $currentPhotoPath;
        $isBicycle = ($validated['vehicle_type'] ?? null) === 'bicicleta';

        if ($request->boolean('remove_photo') && $currentPhotoPath) {
            Storage::disk('public')->delete($currentPhotoPath);
            $photoPath = null;
        }

        if ($request->hasFile('photo')) {
            if ($currentPhotoPath) {
                Storage::disk('public')->delete($currentPhotoPath);
            }

            $photoPath = $request->file('photo')->store('delivery-drivers', 'public');
        }

        return [
            'name' => $validated['name'],
            'document_number' => $validated['document_number'] ?? null,
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'photo_path' => $photoPath,
            'vehicle_type' => $validated['vehicle_type'],
            'vehicle_plate' => $isBicycle ? null : ($validated['vehicle_plate'] ?? null),
            'vehicle_model' => $isBicycle ? null : ($validated['vehicle_model'] ?? null),
            'vehicle_color' => $validated['vehicle_color'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'is_active' => $validated['is_active'],
        ];
    }

    private function driverPermissions(): array
    {
        return ['delivery_drivers.view', 'delivery_drivers.create', 'delivery_drivers.edit', 'delivery_drivers.delete'];
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
