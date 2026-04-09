<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\TableOrder;
use App\Models\TableOrderItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TableManagementController extends Controller
{
    public function index()
    {
        if ($response = $this->denyIfUnauthorized($this->tableModulePermissions())) {
            return $response;
        }

        $tables = RestaurantTable::query()
            ->with(['openOrder.items'])
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
                'openOrders' => TableOrder::query()->where('status', 'open')->count(),
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

        $table = RestaurantTable::create($this->buildTablePayload($validated, $request));

        return redirect()
            ->route('tables.show', $table)
            ->with('success', 'Mesa creada correctamente.');
    }

    public function show(RestaurantTable $table)
    {
        if ($response = $this->denyIfUnauthorized($this->tableModulePermissions())) {
            return $response;
        }

        $table->load([
            'openOrder.items.product',
            'openOrder.openedBy',
            'orders' => fn ($query) => $query
                ->with(['items', 'previousTable'])
                ->latest()
                ->take(10),
        ]);

        $openOrder = $table->openOrder;
        $splitSummary = $openOrder ? $openOrder->splitSummary() : collect();

        return view('tables.show', [
            'restaurantTable' => $table,
            'openOrder' => $openOrder,
            'splitSummary' => $splitSummary,
            'availableProducts' => $this->availableProducts(),
            'orderRows' => $this->orderRows(),
            'transferTargets' => $this->transferTargets($table),
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
            ->route('tables.show', $table)
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

    public function storeOrder(Request $request, RestaurantTable $table): Response|RedirectResponse
    {
        if ($response = $this->denyIfUnauthorized($this->orderManagementPermissions())) {
            return $response;
        }

        if (! $table->is_active) {
            abort(404);
        }

        $validated = $request->validate([
            'customer_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $rows = collect($request->input('items', []))
            ->map(function (array $row): array {
                return [
                    'product_id' => isset($row['product_id']) ? (int) $row['product_id'] : null,
                    'quantity' => isset($row['quantity']) ? (int) $row['quantity'] : null,
                ];
            })
            ->filter(fn (array $row) => !empty($row['product_id']) && !empty($row['quantity']))
            ->values();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Debes agregar al menos un producto al pedido de la mesa.',
            ]);
        }

        DB::transaction(function () use ($validated, $rows, $table): void {
            $order = $table->orders()
                ->where('status', 'open')
                ->first();

            if (! $order) {
                $order = $table->orders()->create([
                    'order_number' => TableOrder::generateOrderNumber(),
                    'customer_name' => $validated['customer_name'] ?? null,
                    'status' => 'open',
                    'opened_by_user_id' => Auth::id(),
                    'notes' => $validated['notes'] ?? null,
                ]);
            } else {
                $order->update([
                    'customer_name' => $validated['customer_name'] ?? $order->customer_name,
                    'notes' => $validated['notes'] ?? $order->notes,
                ]);
            }

            $products = Product::query()
                ->whereIn('id', $rows->pluck('product_id'))
                ->where('active', true)
                ->get()
                ->keyBy('id');

            foreach ($rows as $row) {
                /** @var Product|null $product */
                $product = $products->get($row['product_id']);

                if (! $product) {
                    throw ValidationException::withMessages([
                        'items' => 'Uno de los productos seleccionados ya no esta disponible.',
                    ]);
                }

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit_price' => $product->price,
                    'quantity' => $row['quantity'],
                    'subtotal' => $product->price * $row['quantity'],
                    'split_group' => 1,
                ]);
            }

            $order->recalculateTotals();
            $table->update(['status' => 'occupied']);
        });

        return redirect()
            ->route('tables.show', $table)
            ->with('success', 'Pedido asignado correctamente a la mesa.');
    }

    public function transferOrder(Request $request, TableOrder $order): Response|RedirectResponse
    {
        if ($response = $this->denyIfUnauthorized($this->orderManagementPermissions())) {
            return $response;
        }

        $validated = $request->validate([
            'target_table_id' => ['required', 'exists:restaurant_tables,id'],
        ]);

        if ($order->status !== 'open') {
            return redirect()
                ->route('tables.show', $order->table)
                ->with('error', 'Solo los pedidos abiertos pueden transferirse.');
        }

        $targetTable = RestaurantTable::query()->findOrFail($validated['target_table_id']);
        $sourceTable = $order->table;

        if ($targetTable->id === $sourceTable->id) {
            return redirect()
                ->route('tables.show', $sourceTable)
                ->with('error', 'Selecciona una mesa diferente para transferir el pedido.');
        }

        if (! $targetTable->is_active || $targetTable->openOrder()->exists()) {
            return redirect()
                ->route('tables.show', $sourceTable)
                ->with('error', 'La mesa destino no esta disponible para recibir el pedido.');
        }

        DB::transaction(function () use ($order, $sourceTable, $targetTable): void {
            $order->update([
                'restaurant_table_id' => $targetTable->id,
                'transferred_from_table_id' => $sourceTable->id,
                'last_transferred_at' => now(),
            ]);

            $sourceTable->update(['status' => 'free']);
            $targetTable->update(['status' => 'occupied']);
        });

        return redirect()
            ->route('tables.show', $targetTable)
            ->with('success', 'Pedido transferido correctamente a la nueva mesa.');
    }

    public function updateSplit(Request $request, TableOrder $order): Response|RedirectResponse
    {
        if ($response = $this->denyIfUnauthorized($this->orderManagementPermissions())) {
            return $response;
        }

        if ($order->status !== 'open') {
            return redirect()
                ->route('tables.show', $order->table)
                ->with('error', 'Solo puedes dividir cuentas de pedidos abiertos.');
        }

        $validated = $request->validate([
            'split_items' => ['required', 'array'],
            'split_items.*' => ['required', 'integer', 'min:1', 'max:8'],
        ]);

        $items = $order->items()->get()->keyBy('id');

        DB::transaction(function () use ($validated, $items): void {
            foreach ($validated['split_items'] as $itemId => $group) {
                /** @var TableOrderItem|null $item */
                $item = $items->get((int) $itemId);

                if ($item) {
                    $item->update(['split_group' => $group]);
                }
            }
        });

        return redirect()
            ->route('tables.show', $order->table)
            ->with('success', 'Las cuentas se dividieron correctamente.');
    }

    public function closeOrder(TableOrder $order): Response|RedirectResponse
    {
        if ($response = $this->denyIfUnauthorized($this->orderManagementPermissions())) {
            return $response;
        }

        if ($order->status !== 'open') {
            return redirect()
                ->route('tables.show', $order->table)
                ->with('info', 'Este pedido ya se encuentra cerrado.');
        }

        DB::transaction(function () use ($order): void {
            $table = $order->table;

            $order->update(['status' => 'paid']);

            if ($table) {
                $table->update(['status' => 'free']);
            }
        });

        return redirect()
            ->route('tables.show', $order->table)
            ->with('success', 'Cuenta cerrada correctamente. La mesa quedo libre.');
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

    private function availableProducts(): Collection
    {
        return Product::query()
            ->where('active', true)
            ->where(function ($query) {
                $query->whereIn('product_type', ['simple', 'combo'])
                    ->orWhereNull('product_type');
            })
            ->orderBy('name')
            ->get(['id', 'name', 'price', 'product_type']);
    }

    private function transferTargets(RestaurantTable $currentTable): Collection
    {
        return RestaurantTable::query()
            ->active()
            ->whereKeyNot($currentTable->id)
            ->whereIn('status', ['free', 'reserved'])
            ->whereDoesntHave('orders', fn ($query) => $query->where('status', 'open'))
            ->orderBy('area')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'area', 'status']);
    }

    private function orderRows(): array
    {
        $oldRows = old('items');

        if (is_array($oldRows) && count($oldRows) > 0) {
            return array_values($oldRows);
        }

        return [[
            'product_id' => null,
            'quantity' => 1,
        ]];
    }

    private function tableModulePermissions(): array
    {
        return ['tables.view', 'tables.create', 'tables.edit', 'tables.delete', 'orders.view', 'orders.create', 'orders.edit'];
    }

    private function orderManagementPermissions(): array
    {
        return ['orders.create', 'orders.edit', 'tables.edit'];
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
