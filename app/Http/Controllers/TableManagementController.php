<?php

namespace App\Http\Controllers;

use App\Models\RestaurantTable;
use App\Models\TableOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
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
            'newTable' => new RestaurantTable(['status' => 'free', 'is_active' => true, 'capacity' => 4]),
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

        return redirect(route('tables.index', ['panel' => 'create']) . '#new-table');
    }

    public function store(Request $request): Response|RedirectResponse
    {
        if ($response = $this->denyIfUnauthorized(['tables.create'])) {
            return $response;
        }

        $validated = $this->validateTableData($request);

        DB::transaction(function () use ($validated, $request): void {
            RestaurantTable::create($this->buildTablePayload($validated, $request));
        });

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
        ]);

        return view('tables.show', [
            'restaurantTable' => $table,
            'openOrder' => $table->openOrder,
        ]);
    }

    public function historyIndex()
    {
        if ($response = $this->denyIfUnauthorized($this->tableModulePermissions())) {
            return $response;
        }

        $tables = RestaurantTable::query()
            ->with(['latestOrder.customer', 'openOrder'])
            ->withCount([
                'orders',
                'orders as open_orders_count' => fn ($query) => $query->where('status', 'open'),
                'orders as paid_orders_count' => fn ($query) => $query->where('status', 'paid'),
            ])
            ->orderBy('area')
            ->orderBy('name')
            ->get();

        return view('tables.history.index', [
            'tables' => $tables,
            'summary' => [
                'tables' => $tables->count(),
                'withHistory' => $tables->where('orders_count', '>', 0)->count(),
                'orders' => $tables->sum('orders_count'),
                'open' => $tables->sum('open_orders_count'),
            ],
        ]);
    }

    public function historyShow(RestaurantTable $table)
    {
        if ($response = $this->denyIfUnauthorized($this->tableModulePermissions())) {
            return $response;
        }

        $table->load([
            'openOrder.customer',
            'latestOrder.customer',
        ])->loadCount([
            'orders',
            'orders as open_orders_count' => fn ($query) => $query->where('status', 'open'),
            'orders as paid_orders_count' => fn ($query) => $query->where('status', 'paid'),
        ]);

        $orders = TableOrder::query()
            ->with(['previousTable', 'openedBy', 'sale', 'customer'])
            ->withCount('items')
            ->where('restaurant_table_id', $table->id)
            ->latest()
            ->paginate(10);

        return view('tables.history.show', [
            'restaurantTable' => $table,
            'openOrder' => $table->openOrder,
            'orders' => $orders,
            'summary' => [
                'total' => (int) $table->orders_count,
                'today' => TableOrder::query()
                    ->where('restaurant_table_id', $table->id)
                    ->whereDate('created_at', today())
                    ->count(),
                'open' => (int) $table->open_orders_count,
                'paid' => (int) $table->paid_orders_count,
            ],
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
            'code' => [$table ? 'required' : 'nullable', 'string', 'max:255', Rule::unique('restaurant_tables', 'code')->ignore($table?->id)],
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
            'code' => filled($validated['code'] ?? null) ? $validated['code'] : $this->generateUniqueTableCode(),
            'area' => $validated['area'] ?? null,
            'capacity' => $validated['capacity'],
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function generateUniqueTableCode(): string
    {
        $existingCodes = RestaurantTable::query()
            ->lockForUpdate()
            ->pluck('code')
            ->all();

        $existingLookup = array_fill_keys($existingCodes, true);
        $sequence = 1;

        do {
            $code = 'M-' . str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
            $sequence++;
        } while (isset($existingLookup[$code]));

        return $code;
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
