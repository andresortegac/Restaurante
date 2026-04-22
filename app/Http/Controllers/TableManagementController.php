<?php

namespace App\Http\Controllers;

use App\Models\RestaurantTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class TableManagementController extends Controller
{
    public function index()
    {
        if ($response = $this->denyIfUnauthorized($this->tableModulePermissions())) {
            return $response;
        }

        $tables = RestaurantTable::query()
            ->with(['openOrder.items', 'openOrder.customer'])
            ->orderBy('area')
            ->orderBy('name')
            ->get();

        return view('tables.index', [
            'tables' => $tables,
            'summary' => [
                'total' => $tables->where('is_active', true)->count(),
                'free' => $tables->where('status', 'free')->where('is_active', true)->count(),
                'occupied' => $tables->where('status', 'occupied')->where('is_active', true)->count(),
                'reserved' => $tables->where('status', 'reserved')->where('is_active', true)->count(),
            ],
        ]);
    }

    public function create()
    {
        if ($response = $this->denyIfUnauthorized(['tables.create'])) {
            return $response;
        }

        return view('tables.form', [
            'pageTitle' => 'Crear mesa',
            'restaurantTable' => new RestaurantTable(['status' => 'free', 'is_active' => true, 'capacity' => 4]),
            'formAction' => route('tables.store'),
            'submitLabel' => 'Guardar mesa',
        ]);
    }

    public function store(Request $request): Response|RedirectResponse
    {
        if ($response = $this->denyIfUnauthorized(['tables.create'])) {
            return $response;
        }

        $validated = $this->validateTableData($request);

        RestaurantTable::create($this->buildTablePayload($validated, $request));

        return redirect()
            ->route('tables.index')
            ->with('success', 'Mesa creada correctamente.');
    }

    public function show(RestaurantTable $table)
    {
        if ($response = $this->denyIfUnauthorized($this->tableModulePermissions())) {
            return $response;
        }

        $table->load([
            'openOrder.customer',
            'openOrder.items.product',
            'openOrder.openedBy',
            'orders' => fn ($query) => $query
                ->with(['items', 'previousTable', 'customer'])
                ->latest()
                ->take(10),
        ]);

        return view('tables.show', [
            'restaurantTable' => $table,
            'openOrder' => $table->openOrder,
            'recentOrders' => $table->orders,
        ]);
    }

    public function edit(RestaurantTable $table)
    {
        if ($response = $this->denyIfUnauthorized(['tables.edit'])) {
            return $response;
        }

        return view('tables.form', [
            'pageTitle' => 'Editar mesa',
            'restaurantTable' => $table,
            'formAction' => route('tables.update', $table),
            'submitLabel' => 'Actualizar mesa',
        ]);
    }

    public function update(Request $request, RestaurantTable $table): Response|RedirectResponse
    {
        if ($response = $this->denyIfUnauthorized(['tables.edit'])) {
            return $response;
        }

        $validated = $this->validateTableData($request, $table);
        $payload = $this->buildTablePayload($validated, $request);

        if ($table->openOrder()->exists()) {
            $payload['status'] = 'occupied';
        }

        $table->update($payload);

        return redirect()
            ->route('tables.index')
            ->with('success', 'Mesa actualizada correctamente.');
    }

    public function destroy(RestaurantTable $table): Response|RedirectResponse
    {
        if ($response = $this->denyIfUnauthorized(['tables.delete'])) {
            return $response;
        }

        if ($table->openOrder()->exists()) {
            return redirect()
                ->route('tables.index')
                ->with('error', 'No puedes eliminar una mesa que tiene un pedido abierto.');
        }

        if ($table->orders()->exists()) {
            $table->update([
                'is_active' => false,
                'status' => 'free',
            ]);

            return redirect()
                ->route('tables.index')
                ->with('warning', 'La mesa tiene historial de pedidos. Se desactivo para proteger la trazabilidad.');
        }

        $table->delete();

        return redirect()
            ->route('tables.index')
            ->with('success', 'Mesa eliminada correctamente.');
    }

    private function validateTableData(Request $request, ?RestaurantTable $table = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', Rule::unique('restaurant_tables', 'code')->ignore($table?->id)],
            'area' => ['nullable', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:1', 'max:30'],
            'status' => ['required', Rule::in(['free', 'occupied', 'reserved'])],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function buildTablePayload(array $validated, Request $request): array
    {
        return [
            'name' => $validated['name'],
            'code' => $validated['code'],
            'area' => $validated['area'] ?? null,
            'capacity' => $validated['capacity'],
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function tableModulePermissions(): array
    {
        return ['tables.view', 'tables.create', 'tables.edit', 'tables.delete'];
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
