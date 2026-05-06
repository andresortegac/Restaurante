<?php

namespace App\Http\Controllers;

use App\Models\Box;
use App\Models\BoxSession;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\TableOrder;
use App\Models\TableOrderItem;
use App\Services\TableOrderBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderManagementController extends Controller
{
    public function __construct(
        private readonly TableOrderBillingService $billingService
    ) {
    }

    public function index()
    {
        if ($response = $this->denyIfUnauthorized($this->orderModulePermissions())) {
            return $response;
        }

        $tables = RestaurantTable::query()
            ->active()
            ->with(['openOrder.items', 'openOrder.customer'])
            ->orderBy('area')
            ->orderBy('name')
            ->get();

        return view('orders.index', [
            'tables' => $tables,
            'summary' => [
                'total' => $tables->count(),
                'free' => $tables->where('status', 'free')->count(),
                'occupied' => $tables->where('status', 'occupied')->count(),
                'reserved' => $tables->where('status', 'reserved')->count(),
                'openOrders' => TableOrder::query()->where('status', 'open')->count(),
            ],
        ]);
    }

    public function history(Request $request)
    {
        if ($response = $this->denyIfUnauthorized($this->orderModulePermissions())) {
            return $response;
        }

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:open,paid,cancelled'],
            'table_id' => ['nullable', 'integer', 'exists:restaurant_tables,id'],
        ]);

        $orders = TableOrder::query()
            ->with(['table', 'previousTable', 'openedBy', 'sale', 'customer'])
            ->withCount('items')
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($nestedQuery) use ($search) {
                    $nestedQuery
                        ->where('order_number', 'like', '%' . $search . '%')
                        ->orWhere('customer_name', 'like', '%' . $search . '%')
                        ->orWhere('notes', 'like', '%' . $search . '%');
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['table_id'] ?? null, fn ($query, int $tableId) => $query->where('restaurant_table_id', $tableId))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('orders.history', [
            'orders' => $orders,
            'filters' => $filters,
            'tables' => RestaurantTable::query()
                ->orderBy('area')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'area']),
            'summary' => [
                'total' => TableOrder::query()->count(),
                'open' => TableOrder::query()->where('status', 'open')->count(),
                'paid' => TableOrder::query()->where('status', 'paid')->count(),
                'today' => TableOrder::query()->whereDate('created_at', today())->count(),
            ],
        ]);
    }

    public function show(RestaurantTable $table)
    {
        if ($response = $this->denyIfUnauthorized($this->orderModulePermissions())) {
            return $response;
        }

        if (! $table->is_active) {
            abort(404);
        }

        $table->load([
            'openOrder.customer',
            'openOrder.items.product',
            'openOrder.openedBy',
        ]);

        $openOrder = $table->openOrder;
        $splitSummary = $openOrder ? $openOrder->splitSummary() : collect();

        return view('orders.show', [
            'restaurantTable' => $table,
            'openOrder' => $openOrder,
            'splitSummary' => $splitSummary,
            'availableProducts' => $this->availableProducts(),
            'availableCustomers' => $this->availableCustomers(),
            'orderRows' => $this->orderRows(),
            'transferTargets' => $this->transferTargets($table),
            'activeBox' => $this->activeBox(),
        ]);
    }

    public function showCheckout(TableOrder $order)
    {
        return redirect()->route('billing.checkout', $order);
    }

    public function storeOrder(Request $request, RestaurantTable $table)
    {
        if ($response = $this->denyIfUnauthorized($this->orderManagementPermissions())) {
            return $response;
        }

        if (! $table->is_active) {
            abort(404);
        }

        $validated = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
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
            ->filter(fn (array $row) => ! empty($row['product_id']) && ! empty($row['quantity']))
            ->values();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Debes agregar al menos un producto al pedido de la mesa.',
            ]);
        }

        $order = null;
        $createdItemIds = [];

        DB::transaction(function () use ($validated, $rows, $table, &$order, &$createdItemIds): void {
            $customer = ! empty($validated['customer_id'])
                ? Customer::query()->whereKey($validated['customer_id'])->where('is_active', true)->first()
                : null;

            if (! empty($validated['customer_id']) && ! $customer) {
                throw ValidationException::withMessages([
                    'customer_id' => 'Selecciona un cliente activo o usa la opcion sin cliente.',
                ]);
            }

            $order = $table->orders()
                ->where('status', 'open')
                ->first();

            if (! $order) {
                $order = $table->orders()->create([
                    'order_number' => TableOrder::generateOrderNumber(),
                    'customer_id' => $customer?->id,
                    'customer_name' => $customer?->name,
                    'status' => 'open',
                    'opened_by_user_id' => Auth::id(),
                    'notes' => $validated['notes'] ?? null,
                ]);
            } else {
                $order->update([
                    'customer_id' => $customer?->id,
                    'customer_name' => $customer?->name,
                    'notes' => $validated['notes'] ?? $order->notes,
                ]);
            }

            $products = Product::query()
                ->visibleInMenu()
                ->whereIn('id', $rows->pluck('product_id'))
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

                $item = $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit_price' => $product->price,
                    'quantity' => $row['quantity'],
                    'subtotal' => $product->price * $row['quantity'],
                    'split_group' => 1,
                ]);

                $createdItemIds[] = $item->id;
            }

            $order->recalculateTotals();
            $table->update(['status' => 'occupied']);
        });

        session()->flash('success', 'Pedido guardado correctamente y enviado a cocina.');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Pedido guardado correctamente y enviado a cocina.',
                'redirectUrl' => route('orders.show', $table),
                'printUrl' => route('orders.kitchen-ticket', [
                    'order' => $order,
                    'items' => implode(',', $createdItemIds),
                ]),
            ]);
        }

        return view('orders.print-bridge', [
            'redirectUrl' => route('orders.show', $table),
            'printUrl' => route('orders.kitchen-ticket', [
                'order' => $order,
                'items' => implode(',', $createdItemIds),
            ]),
        ]);
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
                ->route('orders.show', $order->table)
                ->with('error', 'Solo los pedidos abiertos pueden transferirse.');
        }

        $targetTable = RestaurantTable::query()->findOrFail($validated['target_table_id']);
        $sourceTable = $order->table;

        if ($targetTable->id === $sourceTable->id) {
            return redirect()
                ->route('orders.show', $sourceTable)
                ->with('error', 'Selecciona una mesa diferente para transferir el pedido.');
        }

        if (! $targetTable->is_active || $targetTable->openOrder()->exists()) {
            return redirect()
                ->route('orders.show', $sourceTable)
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
            ->route('orders.show', $targetTable)
            ->with('success', 'Pedido transferido correctamente a la nueva mesa.');
    }

    public function updateSplit(Request $request, TableOrder $order): Response|RedirectResponse
    {
        if ($response = $this->denyIfUnauthorized($this->orderManagementPermissions())) {
            return $response;
        }

        if ($order->status !== 'open') {
            return redirect()
                ->route('orders.show', $order->table)
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
            ->route('orders.show', $order->table)
            ->with('success', 'Las cuentas se dividieron correctamente.');
    }

    public function closeOrder(TableOrder $order): Response|RedirectResponse
    {
        if ($response = $this->denyIfUnauthorized($this->orderManagementPermissions())) {
            return $response;
        }

        if ($order->status !== 'open') {
            return redirect()
                ->route('orders.show', $order->table)
                ->with('info', 'Este pedido ya se encuentra cerrado.');
        }

        return redirect()
            ->route('billing.checkout', $order)
            ->with('info', 'Registra el cobro desde facturación para cerrar la cuenta y liberar la mesa.');
    }

    public function processCheckout(Request $request, TableOrder $order)
    {
        if ($response = $this->denyIfUnauthorized(['billing.charge'])) {
            return $response;
        }

        $validated = $request->validate([
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'amount_received' => ['required', 'numeric', 'min:0'],
            'tip_amount' => ['nullable', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:255'],
            'document_type' => ['nullable', 'in:ticket,electronic'],
        ]);
        $result = $this->billingService->checkout($order, $validated, Auth::id());
        $sale = $result['sale'];
        $table = $result['table'];
        $invoice = $result['invoice'];
        $documentWarning = $result['document_warning'];

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $documentWarning
                    ? 'Cobro registrado correctamente, pero el documento quedó con novedad: ' . $documentWarning
                    : 'Cobro registrado correctamente. La mesa fue cerrada y el documento quedó listo para imprimir.',
                'printUrl' => route('pos.sales.print', $sale),
                'redirectUrl' => route('billing.history'),
                'invoiceStatus' => $invoice?->status,
                'cufe' => $invoice?->cufe,
            ]);
        }

        session()->flash(
            $documentWarning ? 'warning' : 'success',
            $documentWarning
                ? 'Cobro registrado correctamente, pero el documento quedó con novedad: ' . $documentWarning
                : 'Cobro registrado correctamente. La venta y el movimiento de caja quedaron guardados.'
        );

        return view('orders.print-bridge', [
            'title' => 'Preparando documento',
            'message' => $documentWarning
                ? 'El cobro quedó guardado. Estamos abriendo el documento y podrás revisar la novedad en historial.'
                : 'Estamos abriendo el documento y en unos segundos volverás al historial de facturación.',
            'primaryActionLabel' => 'Abrir documento',
            'secondaryActionLabel' => 'Ir a facturación',
            'redirectUrl' => route('billing.history'),
            'printUrl' => route('pos.sales.print', $sale),
        ]);
    }

    public function printKitchenTicket(Request $request, TableOrder $order)
    {
        if ($response = $this->denyIfUnauthorized(['orders.view', 'orders.edit'])) {
            return $response;
        }

        $order->load(['table', 'openedBy', 'items.product']);

        $selectedIds = collect(explode(',', (string) $request->query('items')))
            ->map(fn (string $id) => (int) trim($id))
            ->filter()
            ->values();

        $items = $selectedIds->isNotEmpty()
            ? $order->items->whereIn('id', $selectedIds)->values()
            : $order->items->values();

        if ($items->isEmpty()) {
            $items = $order->items->values();
        }

        return view('orders.print-kitchen', [
            'order' => $order,
            'items' => $items,
            'isPartialTicket' => $selectedIds->isNotEmpty(),
        ]);
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

    private function availableCustomers(): Collection
    {
        return Customer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'document_number', 'phone', 'email']);
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

    private function orderModulePermissions(): array
    {
        return ['orders.view', 'orders.create', 'orders.edit'];
    }

    private function orderManagementPermissions(): array
    {
        return ['orders.create', 'orders.edit'];
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

    private function paymentMethods(): Collection
    {
        return PaymentMethod::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
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
