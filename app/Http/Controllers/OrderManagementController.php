<?php

namespace App\Http\Controllers;

use App\Models\Box;
use App\Models\BoxAuditLog;
use App\Models\BoxMovement;
use App\Models\BoxSession;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\Sale;
use App\Models\TableOrder;
use App\Services\TableOrderBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $orders = TableOrder::query()
            ->with(['table', 'previousTable', 'openedBy', 'sale', 'customer'])
            ->withCount('items')
            ->where(function ($query): void {
                $query->whereDoesntHave('sale')
                    ->orWhereHas('sale', function ($saleQuery): void {
                        $saleQuery
                            ->where('status', '<>', 'voided')
                            ->whereDoesntHave('invoice', fn ($invoiceQuery) => $invoiceQuery->where('status', 'voided'));
                    });
            })
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
            ->when($filters['date_from'] ?? null, fn ($query, string $dateFrom) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn ($query, string $dateTo) => $query->whereDate('created_at', '<=', $dateTo))
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
                'total' => $this->visibleOrdersQuery()->count(),
                'open' => TableOrder::query()->where('status', 'open')->count(),
                'paid' => $this->visibleOrdersQuery()->where('status', 'paid')->count(),
                'today' => $this->visibleOrdersQuery()->whereDate('created_at', today())->count(),
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

        return view('orders.show', [
            'restaurantTable' => $table,
            'openOrder' => $table->openOrder,
            'availableProducts' => $this->availableProducts(),
            'orderRows' => $this->orderRows(),
            'transferTargets' => $this->transferTargets($table),
            'activeBox' => $this->activeBox(),
        ]);
    }

    public function showCheckout(TableOrder $order)
    {
        return redirect()->route('billing.checkout', $order);
    }

    public function edit(TableOrder $order)
    {
        $order->load(['table', 'items.product', 'openedBy', 'sale.payments.paymentMethod', 'sale.boxMovements.session', 'sale.invoice']);

        $this->assertOrderCanBeEdited($order);

        return view('orders.edit', [
            'order' => $order,
            'products' => $this->editableProducts($order),
            'canAdjustPaidSale' => $this->canAdjustPaidSale($order),
        ]);
    }

    public function update(Request $request, TableOrder $order): RedirectResponse|Response
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
        ]);

        $updatedOrder = null;

        DB::transaction(function () use ($order, $validated, &$updatedOrder): void {
            $currentOrder = TableOrder::query()
                ->with(['items', 'sale.items', 'sale.payments.paymentMethod', 'sale.boxMovements.session', 'sale.invoice'])
                ->lockForUpdate()
                ->findOrFail($order->id);

            $this->assertOrderCanBeEdited($currentOrder);

            if ($currentOrder->sale && ! $this->canAdjustPaidSale($currentOrder)) {
                throw ValidationException::withMessages([
                    'items' => 'Este pedido ya pertenece a una caja cerrada. No se puede ajustar el pedido, recibo ni cierre.',
                ]);
            }

            $rows = $this->normalizedSubmittedRows($validated['items']);

            if ($rows->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'El pedido debe conservar al menos un producto.',
                ]);
            }

            $products = Product::query()
                ->whereIn('products.id', $rows->pluck('product_id'))
                ->get()
                ->keyBy('id');

            $currentOrder->items()->delete();

            foreach ($rows as $row) {
                $product = $products->get($row['product_id']);

                if (! $product) {
                    throw ValidationException::withMessages([
                        'items' => 'Uno de los productos seleccionados ya no existe.',
                    ]);
                }

                $currentOrder->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit_price' => $product->price,
                    'quantity' => $row['quantity'],
                    'subtotal' => money_value((float) $product->price * (int) $row['quantity']),
                    'split_group' => 1,
                ]);
            }

            $currentOrder->notes = $validated['notes'] ?? null;
            $currentOrder->save();
            $currentOrder->load('items.product.taxRate');
            $currentOrder->recalculateTotals();

            if ($currentOrder->sale) {
                $this->syncSaleFromEditedOrder($currentOrder);
            }

            $updatedOrder = $currentOrder->fresh(['table', 'sale.invoice']);
        });

        $redirect = $updatedOrder->table
            ? redirect()->route('orders.show', $updatedOrder->table)
            : redirect()->route('orders.edit', $updatedOrder);

        return $redirect->with('success', $updatedOrder->sale
            ? 'Pedido, recibo y caja ajustados correctamente.'
            : 'Pedido actualizado correctamente. Puedes reenviar la comanda a cocina.');
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
            $order = $table->orders()
                ->where('status', 'open')
                ->first();

            if (! $order) {
                $order = $table->orders()->create([
                    'order_number' => TableOrder::generateOrderNumber(),
                    'customer_id' => null,
                    'customer_name' => null,
                    'status' => 'open',
                    'opened_by_user_id' => Auth::id(),
                    'notes' => $validated['notes'] ?? null,
                ]);
            } else {
                $order->update([
                    'notes' => $validated['notes'] ?? $order->notes,
                ]);
            }

            $products = Product::query()
                ->visibleInMenu()
                ->whereIn('products.id', $rows->pluck('product_id'))
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
            'customer_id' => ['nullable', 'exists:customers,id'],
            'payment_method_id' => ['nullable', Rule::exists('payment_methods', 'id')->where('active', true)->whereIn('code', PaymentMethod::SYSTEM_ALLOWED_CODES)],
            'amount_received' => ['required', 'numeric', 'min:0'],
            'tip_amount' => ['nullable', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:255'],
            'document_type' => ['nullable', 'in:ticket,electronic'],
            'is_credit' => ['nullable', 'boolean'],
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

    private function editableProducts(TableOrder $order): Collection
    {
        $currentProductIds = $order->items
            ->pluck('product_id')
            ->filter()
            ->values();

        return Product::query()
            ->with('menuCategory:id,name,description,sort_order,is_active')
            ->where(function ($query) use ($currentProductIds): void {
                $query->visibleInMenu();

                if ($currentProductIds->isNotEmpty()) {
                    $query->orWhereIn('products.id', $currentProductIds);
                }
            })
            ->orderedForMenu()
            ->get();
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
            ->systemAllowed()
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    private function assertOrderCanBeEdited(TableOrder $order): void
    {
        if (! in_array($order->status, ['open', 'paid'], true)) {
            throw ValidationException::withMessages([
                'items' => 'Solo se pueden editar pedidos abiertos o cobrados.',
            ]);
        }

        if ($order->sale?->isVoided() || $order->sale?->invoice?->isVoided()) {
            throw ValidationException::withMessages([
                'items' => 'No se puede editar un pedido con factura anulada.',
            ]);
        }
    }

    private function canAdjustPaidSale(TableOrder $order): bool
    {
        if (! $order->sale) {
            return true;
        }

        return $order->sale->canBeEditedInOpenCashSession();
    }

    private function normalizedSubmittedRows(array $items): Collection
    {
        return collect($items)
            ->map(fn (array $row): array => [
                'product_id' => (int) ($row['product_id'] ?? 0),
                'quantity' => max(0, (int) ($row['quantity'] ?? 0)),
            ])
            ->filter(fn (array $row): bool => $row['product_id'] > 0 && $row['quantity'] > 0)
            ->groupBy('product_id')
            ->map(fn (Collection $rows, int $productId): array => [
                'product_id' => $productId,
                'quantity' => (int) $rows->sum('quantity'),
            ])
            ->values();
    }

    private function syncSaleFromEditedOrder(TableOrder $order): void
    {
        $sale = $order->sale;
        $sale->loadMissing(['items', 'payments.paymentMethod', 'boxMovements.session', 'invoice']);

        $oldTotal = money_value((float) $sale->total);

        $sale->items()->delete();

        foreach ($order->items as $item) {
            $sale->items()->create([
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'subtotal' => $item->subtotal,
            ]);
        }

        $sale->notes = collect([
            'Pedido ' . $order->order_number,
            $order->table ? 'Mesa ' . $order->table->name : null,
            $order->notes ? 'Notas: ' . $order->notes : null,
        ])->filter()->implode(' | ') ?: null;
        $sale->save();
        $sale->unsetRelation('items');
        $sale->calculateTotal();
        $sale->refresh();

        $delta = money_value((float) $sale->total - $oldTotal);

        $payment = $sale->payments->first();

        if ($payment) {
            $paymentMethodCode = strtoupper((string) ($payment->paymentMethod?->code ?? ''));
            $movementImpact = money_value((float) $sale->boxMovements->sum('amount'));
            $affectsCash = $paymentMethodCode === 'CASH' || ($paymentMethodCode === '' && abs($movementImpact) > 0.009);

            $payment->update([
                'amount' => $sale->total,
                'received_amount' => $sale->payment_status === 'paid'
                    ? money_value(max(0, (float) $payment->received_amount + $delta))
                    : $payment->received_amount,
            ]);

            if ($sale->payment_status === 'paid') {
                $this->adjustSaleBoxMovements($sale, $delta, $affectsCash);
            }
        }

        if ($sale->customerCredit) {
            $newBalance = money_value(max(0, (float) $sale->customerCredit->balance + $delta));

            $sale->customerCredit->update([
                'amount' => money_value(max(0, (float) $sale->customerCredit->amount + $delta)),
                'balance' => $newBalance,
                'status' => $newBalance > 0 ? 'pending' : 'paid',
            ]);
        }

        if ($sale->invoice) {
            $sale->invoice->update([
                'status_message' => 'Recibo actualizado por edicion del pedido ' . $order->order_number . '.',
            ]);
        }
    }

    private function adjustSaleBoxMovements(Sale $sale, float $delta, bool $affectsCash): void
    {
        $movement = $sale->boxMovements
            ->where('movement_type', 'table_order_payment')
            ->first();

        if (! $movement) {
            return;
        }

        $boxImpactDelta = $affectsCash ? money_value($delta) : 0.0;
        $newAmount = money_value((float) $movement->amount + $boxImpactDelta);

        $movement->update([
            'amount' => $newAmount,
            'balance_after' => money_value((float) $movement->balance_before + $newAmount),
            'description' => trim((string) $movement->description) . ' | Ajustado por edicion de pedido $' . money($delta),
        ]);

        if ($movement->box_id && $movement->box_session_id) {
            BoxAuditLog::query()->create([
                'box_id' => $movement->box_id,
                'box_session_id' => $movement->box_session_id,
                'user_id' => Auth::id(),
                'action' => 'order_edited',
                'description' => 'Ajuste de pedido cobrado #' . $sale->table_order_id . ' por $' . money($delta),
                'metadata' => [
                    'sale_id' => $sale->id,
                    'movement_id' => $movement->id,
                    'delta' => $delta,
                    'box_impact_delta' => $boxImpactDelta,
                    'new_sale_total' => (float) $sale->total,
                ],
                'occurred_at' => now(),
            ]);
        }
    }

    private function denyIfUnauthorized(array $permissions): ?Response
    {
        $user = auth()->user();

        if ($user && ($user->hasRole('Admin') || $user->hasAnyPermission($permissions))) {
            return null;
        }

        return response()->view('errors.403', [], 403);
    }

    private function visibleOrdersQuery()
    {
        return TableOrder::query()
            ->where(function ($query): void {
                $query->whereDoesntHave('sale')
                    ->orWhereHas('sale', function ($saleQuery): void {
                        $saleQuery
                            ->where('status', '<>', 'voided')
                            ->whereDoesntHave('invoice', fn ($invoiceQuery) => $invoiceQuery->where('status', 'voided'));
                    });
            });
    }
}
