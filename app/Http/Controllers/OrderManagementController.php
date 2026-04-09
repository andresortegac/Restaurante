<?php

namespace App\Http\Controllers;

use App\Models\Box;
use App\Models\BoxMovement;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\Sale;
use App\Models\TableOrder;
use App\Models\TableOrderItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderManagementController extends Controller
{
    public function index()
    {
        if ($response = $this->denyIfUnauthorized($this->orderModulePermissions())) {
            return $response;
        }

        $tables = RestaurantTable::query()
            ->active()
            ->with(['openOrder.items'])
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

    public function show(RestaurantTable $table)
    {
        if ($response = $this->denyIfUnauthorized($this->orderModulePermissions())) {
            return $response;
        }

        if (! $table->is_active) {
            abort(404);
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

        return view('orders.show', [
            'restaurantTable' => $table,
            'openOrder' => $openOrder,
            'splitSummary' => $splitSummary,
            'availableProducts' => $this->availableProducts(),
            'orderRows' => $this->orderRows(),
            'transferTargets' => $this->transferTargets($table),
            'recentOrders' => $table->orders,
            'activeBox' => $this->activeBox(),
        ]);
    }

    public function showCheckout(TableOrder $order)
    {
        if ($response = $this->denyIfUnauthorized($this->orderManagementPermissions())) {
            return $response;
        }

        $order->load([
            'table',
            'items.product',
            'openedBy',
            'sale',
        ]);

        if ($order->status !== 'open') {
            return redirect()
                ->route('orders.show', $order->table)
                ->with('info', 'Este pedido ya fue cerrado y no requiere cobro adicional.');
        }

        return view('orders.payment', [
            'order' => $order,
            'restaurantTable' => $order->table,
            'splitSummary' => $order->splitSummary(),
            'activeBox' => $this->activeBox(),
            'paymentMethods' => $this->paymentMethods(),
        ]);
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
            ->route('orders.checkout', $order)
            ->with('info', 'Registra el cobro para cerrar la cuenta y liberar la mesa.');
    }

    public function processCheckout(Request $request, TableOrder $order)
    {
        if ($response = $this->denyIfUnauthorized($this->orderManagementPermissions())) {
            return $response;
        }

        $validated = $request->validate([
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'amount_received' => ['required', 'numeric', 'min:0'],
            'tip_amount' => ['nullable', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $paymentMethod = PaymentMethod::query()
            ->whereKey($validated['payment_method_id'])
            ->where('active', true)
            ->first();

        if (! $paymentMethod) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'Selecciona un metodo de pago activo.',
            ]);
        }

        $tipAmount = round((float) ($validated['tip_amount'] ?? 0), 2);
        $amountReceived = round((float) $validated['amount_received'], 2);
        $sale = null;
        $table = null;

        DB::transaction(function () use ($order, $paymentMethod, $validated, $tipAmount, $amountReceived, &$sale, &$table): void {
            $currentOrder = TableOrder::query()
                ->with(['items.product', 'table', 'sale'])
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($currentOrder->status !== 'open') {
                throw ValidationException::withMessages([
                    'payment_method_id' => 'Este pedido ya fue cerrado.',
                ]);
            }

            if ($currentOrder->sale) {
                throw ValidationException::withMessages([
                    'payment_method_id' => 'Este pedido ya tiene una venta registrada.',
                ]);
            }

            $table = $currentOrder->table;
            $box = Box::query()
                ->where('status', 'open')
                ->orderByDesc('opened_at')
                ->lockForUpdate()
                ->first();

            if (! $box) {
                throw ValidationException::withMessages([
                    'payment_method_id' => 'No hay una caja abierta para registrar este cobro.',
                ]);
            }

            $amountDue = round((float) $currentOrder->total + $tipAmount, 2);
            $isCashPayment = $this->isCashPaymentMethod($paymentMethod);

            if ($amountReceived < $amountDue) {
                throw ValidationException::withMessages([
                    'amount_received' => 'El monto recibido no cubre el total mas la propina.',
                ]);
            }

            if (! $isCashPayment && abs($amountReceived - $amountDue) > 0.009) {
                throw ValidationException::withMessages([
                    'amount_received' => 'Para pagos distintos a efectivo, el monto recibido debe ser igual al total mas la propina.',
                ]);
            }

            $changeAmount = $isCashPayment
                ? round(max(0, $amountReceived - $amountDue), 2)
                : 0.0;

            $saleNotes = collect([
                'Pedido ' . $currentOrder->order_number,
                $table ? 'Mesa ' . $table->name : null,
                $currentOrder->notes ? 'Notas: ' . $currentOrder->notes : null,
            ])->filter()->implode(' | ');

            $sale = Sale::query()->create([
                'user_id' => Auth::id(),
                'box_id' => $box->id,
                'table_order_id' => $currentOrder->id,
                'customer_name' => $currentOrder->customer_name,
                'status' => 'completed',
                'notes' => $saleNotes !== '' ? $saleNotes : null,
            ]);

            foreach ($currentOrder->items as $item) {
                $sale->items()->create([
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'subtotal' => $item->subtotal,
                ]);
            }

            $sale->calculateTotal();

            $payment = $sale->payments()->create([
                'payment_method_id' => $paymentMethod->id,
                'amount' => $sale->total,
                'received_amount' => $amountReceived,
                'change_amount' => $changeAmount,
                'tip_amount' => $tipAmount,
                'reference' => $validated['reference'] ?? null,
                'status' => 'completed',
            ]);

            $movementTotal = (float) BoxMovement::query()
                ->where('box_id', $box->id)
                ->lockForUpdate()
                ->sum('amount');
            $balanceBefore = round((float) $box->opening_balance + $movementTotal, 2);
            $boxImpact = $this->boxImpactAmount((float) $sale->total, $tipAmount, $paymentMethod);
            $balanceAfter = round($balanceBefore + $boxImpact, 2);

            $box->movements()->create([
                'sale_id' => $sale->id,
                'payment_id' => $payment->id,
                'user_id' => Auth::id(),
                'movement_type' => 'table_order_payment',
                'amount' => $boxImpact,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $this->movementDescription($currentOrder, $table, $paymentMethod, $boxImpact),
                'occurred_at' => now(),
            ]);

            $currentOrder->update(['status' => 'paid']);

            if ($table) {
                $table->update(['status' => 'free']);
            }
        });

        session()->flash('success', 'Cobro registrado correctamente. La venta y el movimiento de caja quedaron guardados.');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Cobro registrado correctamente. La mesa fue cerrada y la factura esta lista para imprimir.',
                'printUrl' => route('pos.sales.print', $sale),
                'redirectUrl' => route('orders.show', $table ?? $order->table),
            ]);
        }

        return view('orders.print-bridge', [
            'title' => 'Preparando factura',
            'message' => 'Estamos abriendo la factura y en unos segundos volveras al detalle de la mesa.',
            'primaryActionLabel' => 'Abrir factura',
            'secondaryActionLabel' => 'Volver al pedido',
            'redirectUrl' => route('orders.show', $table ?? $order->table),
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
        return Box::query()
            ->where('status', 'open')
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

    private function isCashPaymentMethod(PaymentMethod $paymentMethod): bool
    {
        return strtoupper((string) $paymentMethod->code) === 'CASH';
    }

    private function boxImpactAmount(float $saleTotal, float $tipAmount, PaymentMethod $paymentMethod): float
    {
        if (! $this->isCashPaymentMethod($paymentMethod)) {
            return 0.0;
        }

        return round($saleTotal + $tipAmount, 2);
    }

    private function movementDescription(TableOrder $order, ?RestaurantTable $table, PaymentMethod $paymentMethod, float $boxImpact): string
    {
        $parts = [
            'Cobro del pedido ' . $order->order_number,
            $table ? 'Mesa ' . $table->name : null,
            'Metodo ' . $paymentMethod->name,
            $boxImpact > 0
                ? 'Impacto en caja $' . number_format($boxImpact, 2, '.', '')
                : 'Sin impacto en caja',
        ];

        return collect($parts)
            ->filter()
            ->implode(' | ');
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
