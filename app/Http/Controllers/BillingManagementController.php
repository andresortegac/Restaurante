<?php

namespace App\Http\Controllers;

use App\Models\Box;
use App\Models\BoxAuditLog;
use App\Models\BoxMovement;
use App\Models\BoxSession;
use App\Models\Customer;
use App\Models\CustomerBalanceMovement;
use App\Models\CustomerCredit;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\TableOrder;
use App\Services\CustomerCreditService;
use App\Services\ManualBillingService;
use App\Services\SaleDocumentService;
use App\Services\TableOrderBillingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BillingManagementController extends Controller
{
    public function __construct(
        private readonly TableOrderBillingService $billingService,
        private readonly ManualBillingService $manualBillingService,
        private readonly SaleDocumentService $saleDocumentService,
        private readonly CustomerCreditService $customerCreditService
    ) {
    }

    public function index(Request $request)
    {
        if ($response = $this->denyIfUnauthorized(['billing.view', 'billing.charge'])) {
            return $response;
        }

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:255'],
        ]);

        $openOrdersQuery = TableOrder::query()
            ->with(['table', 'customer', 'openedBy'])
            ->withCount('items')
            ->where('status', 'open')
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($nestedQuery) use ($search) {
                    $nestedQuery
                        ->where('order_number', 'like', '%' . $search . '%')
                        ->orWhere('customer_name', 'like', '%' . $search . '%')
                        ->orWhereHas('table', fn ($tableQuery) => $tableQuery
                            ->where('name', 'like', '%' . $search . '%')
                            ->orWhere('code', 'like', '%' . $search . '%'));
                });
            })
            ->when($filters['area'] ?? null, fn ($query, string $area) => $query->whereHas('table', fn ($tableQuery) => $tableQuery->where('area', $area)));

        $openOrders = $openOrdersQuery
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $summaryQuery = TableOrder::query()->where('status', 'open');

        return view('billing.index', [
            'openOrders' => $openOrders,
            'filters' => $filters,
            'areas' => TableOrder::query()
                ->join('restaurant_tables', 'table_orders.restaurant_table_id', '=', 'restaurant_tables.id')
                ->where('table_orders.status', 'open')
                ->whereNotNull('restaurant_tables.area')
                ->distinct()
                ->orderBy('restaurant_tables.area')
                ->pluck('restaurant_tables.area'),
            'summary' => [
                'openOrders' => (clone $summaryQuery)->count(),
                'tables' => (clone $summaryQuery)->distinct('restaurant_table_id')->count('restaurant_table_id'),
                'totalDue' => (float) (clone $summaryQuery)->sum('total'),
            ],
        ]);
    }

    public function showCheckout(TableOrder $order)
    {
        if ($response = $this->denyIfUnauthorized(['billing.view', 'billing.charge'])) {
            return $response;
        }

        $order->load([
            'customer',
            'customer.pendingCredits',
            'table',
            'items.product',
            'openedBy',
            'sale',
        ]);

        if ($order->status !== 'open') {
            return redirect()
                ->route('billing.history')
                ->with('info', 'Este pedido ya fue cobrado y ahora forma parte del historial.');
        }

        $billingReadiness = $this->saleDocumentService->electronicInvoiceStatus($order->customer);

        return view('billing.checkout', [
            'order' => $order,
            'restaurantTable' => $order->table,
            'splitSummary' => collect(),
            'activeBox' => $this->activeBox(),
            'paymentMethods' => $this->paymentMethods(),
            'customers' => Customer::query()
                ->withSum(['pendingCredits as pending_credit_total' => fn ($query) => $query->where('status', 'pending')], 'balance')
                ->where(function ($query) use ($order) {
                    $query->where('is_active', true);

                    if ($order->customer_id) {
                        $query->orWhere('id', $order->customer_id);
                    }
                })
                ->orderBy('name')
                ->limit(200)
                ->get(['id', 'name', 'document_number', 'billing_identification', 'email', 'phone', 'available_balance']),
            'billingReadiness' => $billingReadiness,
            'customerPendingCreditTotal' => (float) ($order->customer?->pendingCredits()->sum('balance') ?? 0),
        ]);
    }

    public function processCheckout(Request $request, TableOrder $order)
    {
        if ($response = $this->denyIfUnauthorized(['billing.charge'])) {
            return $response;
        }

        $validated = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'payment_method_id' => ['nullable', $this->allowedPaymentMethodRule()],
            'amount_received' => ['required', 'numeric', 'min:0'],
            'document_type' => ['nullable', 'in:ticket,electronic'],
            'payment_mode' => ['nullable', 'in:paid_now,customer_balance'],
            'is_credit' => ['nullable', 'boolean'],
            'apply_customer_balance' => ['nullable', 'boolean'],
        ]);

        $paymentMode = $validated['payment_mode'] ?? null;
        $validated['is_credit'] = false;
        $validated['apply_customer_balance'] = $paymentMode
            ? $paymentMode === 'customer_balance'
            : $request->boolean('apply_customer_balance');

        $result = $this->billingService->checkout($order, $validated, Auth::id());
        $sale = $result['sale'];
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

    public function showManualCheckout()
    {
        if ($response = $this->denyIfUnauthorized(['billing.view', 'billing.charge'])) {
            return $response;
        }

        return view('billing.manual-checkout', [
            'activeBox' => $this->activeBox(),
            'paymentMethods' => $this->paymentMethods(),
            'products' => Product::query()
                ->with('menuCategory:id,name,description,sort_order,is_active')
                ->visibleInMenu()
                ->orderedForMenu()
                ->get(),
            'customers' => Customer::query()
                ->withSum(['pendingCredits as pending_credit_total' => fn ($query) => $query->where('status', 'pending')], 'balance')
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(200)
                ->get(['id', 'name', 'document_number', 'billing_identification', 'email', 'phone', 'billing_address', 'available_balance']),
            'billingReadiness' => $this->saleDocumentService->electronicInvoiceStatus(null),
        ]);
    }

    public function processManualCheckout(Request $request)
    {
        if ($response = $this->denyIfUnauthorized(['billing.charge'])) {
            return $response;
        }

        $validated = $request->validate([
            'origin_type' => ['required', 'in:table,delivery'],
            'origin_reference' => ['nullable', 'string', 'max:255'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'document_type' => ['nullable', 'in:ticket,electronic'],
            'payment_method_id' => ['nullable', $this->allowedPaymentMethodRule()],
            'amount_received' => ['required', 'numeric', 'min:0'],
            'tip_amount' => ['nullable', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:255'],
            'payment_mode' => ['nullable', 'in:paid_now,customer_balance,credit,customer_balance_credit'],
            'is_credit' => ['nullable', 'boolean'],
            'apply_customer_balance' => ['nullable', 'boolean'],
            'credit_due_at' => ['nullable', 'date', 'after_or_equal:today'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0.01'],
        ]);

        $paymentMode = $validated['payment_mode'] ?? null;
        $validated['is_credit'] = $paymentMode
            ? in_array($paymentMode, ['credit', 'customer_balance_credit'], true)
            : $request->boolean('is_credit');
        $validated['apply_customer_balance'] = $paymentMode
            ? in_array($paymentMode, ['customer_balance', 'customer_balance_credit'], true)
            : $request->boolean('apply_customer_balance');

        $result = $this->manualBillingService->checkout($validated, Auth::id());
        $sale = $result['sale'];
        $invoice = $result['invoice'];
        $documentWarning = $result['document_warning'];

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $documentWarning
                    ? 'Cobro manual registrado, pero el documento quedo con novedad: ' . $documentWarning
                    : 'Cobro manual registrado correctamente. La comanda quedo lista para cocina.',
                'printUrl' => route('pos.sales.print', ['sale' => $sale, 'return_to' => route('billing.manual', [], false)]),
                'documentUrl' => route('pos.sales.print', ['sale' => $sale, 'return_to' => route('billing.manual', [], false)]),
                'kitchenPrintUrl' => route('billing.manual.kitchen-ticket', ['sale' => $sale, 'return_to' => route('billing.manual', [], false)]),
                'redirectUrl' => route('billing.history'),
                'invoiceStatus' => $invoice?->status,
                'cufe' => $invoice?->cufe,
            ]);
        }

        session()->flash(
            $documentWarning ? 'warning' : 'success',
            $documentWarning
                ? 'Cobro manual registrado, pero el documento quedo con novedad: ' . $documentWarning
                : 'Cobro manual registrado correctamente. La comanda quedo lista para cocina.'
        );

        return view('orders.print-bridge', [
            'title' => 'Preparando factura y comanda',
            'message' => 'Estamos abriendo la comanda para cocina y la factura para entregar al cliente.',
            'primaryActionLabel' => 'Abrir factura',
            'secondaryActionLabel' => 'Ir a facturacion',
            'redirectUrl' => route('billing.history'),
            'printUrl' => route('pos.sales.print', ['sale' => $sale, 'return_to' => route('billing.manual', [], false)]),
            'secondaryPrintUrl' => route('billing.manual.kitchen-ticket', ['sale' => $sale, 'return_to' => route('billing.manual', [], false)]),
            'secondaryPrintLabel' => 'Abrir comanda',
        ]);
    }

    public function printManualKitchenTicket(Request $request, Sale $sale)
    {
        if ($response = $this->denyIfUnauthorized(['billing.view', 'billing.charge'])) {
            return $response;
        }

        $sale->load(['items.product', 'customer', 'user', 'invoice']);
        $returnTo = $this->internalReturnUrl($request->query('return_to'), $request) ?? route('billing.manual', [], false);

        return view('billing.manual-kitchen-ticket', [
            'sale' => $sale,
            'documentUrl' => route('pos.sales.print', ['sale' => $sale, 'return_to' => $returnTo]),
            'returnTo' => $returnTo,
            'printedAt' => now(),
        ]);
    }

    public function editSale(Sale $sale)
    {
        if ($response = $this->denyIfUnauthorized(['billing.view', 'billing.charge', 'billing.history'])) {
            return $response;
        }

        $sale->load(['items.product', 'payments.paymentMethod', 'boxMovements.session', 'invoice', 'tableOrder', 'delivery.items']);

        if ($sale->tableOrder) {
            return redirect()->route('orders.edit', $sale->tableOrder);
        }

        $this->assertSaleCanBeEdited($sale);

        return view('billing.sale-edit', [
            'sale' => $sale,
            'products' => $this->editableProductsForSale($sale),
            'canAdjustSale' => $this->canAdjustSale($sale),
        ]);
    }

    public function updateSale(Request $request, Sale $sale)
    {
        if ($response = $this->denyIfUnauthorized(['billing.charge'])) {
            return $response;
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($sale, $validated): void {
            $currentSale = Sale::query()
                ->with(['items', 'payments.paymentMethod', 'boxMovements.session', 'invoice', 'delivery.items', 'tableOrder'])
                ->lockForUpdate()
                ->findOrFail($sale->id);

            if ($currentSale->tableOrder) {
                throw ValidationException::withMessages([
                    'items' => 'Las ventas de mesa se editan desde el pedido.',
                ]);
            }

            $this->assertSaleCanBeEdited($currentSale);

            if (! $this->canAdjustSale($currentSale)) {
                throw ValidationException::withMessages([
                    'items' => 'Esta factura pertenece a una caja cerrada. No se puede ajustar el recibo ni el cierre.',
                ]);
            }

            $rows = $this->normalizedSaleRows($validated['items']);

            if ($rows->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'La factura debe conservar al menos un producto.',
                ]);
            }

            $products = Product::query()
                ->whereIn('products.id', $rows->pluck('product_id'))
                ->get()
                ->keyBy('id');

            $oldTotal = money_value((float) $currentSale->total);
            $deliveryFee = money_value((float) ($currentSale->delivery?->delivery_fee ?? 0));

            $currentSale->items()->delete();
            $deliveryItems = collect();

            foreach ($rows as $row) {
                $product = $products->get($row['product_id']);

                if (! $product) {
                    throw ValidationException::withMessages([
                        'items' => 'Uno de los productos seleccionados ya no existe.',
                    ]);
                }

                $quantity = (int) $row['quantity'];
                $subtotal = money_value((float) $product->price * $quantity);

                $currentSale->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'unit_price' => $product->price,
                    'subtotal' => $subtotal,
                ]);

                $deliveryItems->push([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'unit_price' => money_value($product->price),
                    'subtotal' => $subtotal,
                ]);
            }

            if ($deliveryFee > 0) {
                $currentSale->items()->create([
                    'product_id' => null,
                    'product_name' => 'Costo domicilio',
                    'quantity' => 1,
                    'unit_price' => $deliveryFee,
                    'subtotal' => $deliveryFee,
                ]);
            }

            $currentSale->notes = $validated['notes'] ?? $currentSale->notes;
            $currentSale->save();
            $currentSale->unsetRelation('items');
            $currentSale->calculateTotal();
            $currentSale->refresh();
            $currentSale->load(['payments.paymentMethod', 'boxMovements.session', 'delivery.items', 'invoice']);

            if ($currentSale->delivery) {
                $orderTotal = money_value((float) $deliveryItems->sum('subtotal'));
                $totalCharge = money_value($orderTotal + $deliveryFee);
                $currentSale->delivery->items()->delete();

                foreach ($deliveryItems as $item) {
                    $currentSale->delivery->items()->create($item);
                }

                $currentSale->delivery->update([
                    'order_total' => $orderTotal,
                    'total_charge' => $totalCharge,
                    'change_required' => max(0, money_value((float) $currentSale->delivery->customer_payment_amount - $totalCharge)),
                ]);
            }

            $delta = money_value((float) $currentSale->total - $oldTotal);
            $this->adjustSalePaymentAndBox($currentSale, $delta);

            if ($currentSale->invoice) {
                $currentSale->invoice->update([
                    'status_message' => 'Recibo actualizado por edicion de factura #' . $currentSale->id . '.',
                ]);
            }
        });

        return redirect()
            ->route('billing.history')
            ->with('success', 'Factura actualizada correctamente. El recibo y la caja abierta quedaron ajustados.');
    }

    private function internalReturnUrl(?string $url, Request $request): ?string
    {
        if (! $url) {
            return null;
        }

        if (str_starts_with($url, '/')) {
            return $url;
        }

        $appHost = parse_url(config('app.url'), PHP_URL_HOST);
        $currentHost = $request->getHost();
        $returnHost = parse_url($url, PHP_URL_HOST);

        return $returnHost === null || $returnHost === $appHost || $returnHost === $currentHost ? $url : null;
    }

    public function history(Request $request)
    {
        if ($response = $this->denyIfUnauthorized(['billing.view', 'billing.history'])) {
            return $response;
        }

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'document_type' => ['nullable', 'in:ticket,electronic'],
            'status' => ['nullable', 'string', 'max:50'],
            'payment_status' => ['nullable', 'in:paid,credit'],
        ]);

        $salesQuery = Sale::query()
            ->with(['user', 'box', 'invoice', 'payments.paymentMethod', 'boxMovements.session', 'tableOrder.table', 'customer', 'delivery', 'customerCredit', 'customerBalanceMovements'])
            ->withCount('items')
            ->where('status', '<>', 'voided')
            ->whereDoesntHave('invoice', fn ($invoiceQuery) => $invoiceQuery->where('status', 'voided'))
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($nestedQuery) use ($search) {
                    $nestedQuery
                        ->where('customer_name', 'like', '%' . $search . '%')
                        ->orWhere('id', 'like', '%' . $search . '%')
                        ->orWhereHas('tableOrder', fn ($orderQuery) => $orderQuery
                            ->where('order_number', 'like', '%' . $search . '%')
                            ->orWhereHas('table', fn ($tableQuery) => $tableQuery
                                ->where('name', 'like', '%' . $search . '%')
                                ->orWhere('code', 'like', '%' . $search . '%')))
                        ->orWhereHas('delivery', fn ($deliveryQuery) => $deliveryQuery
                            ->where('delivery_number', 'like', '%' . $search . '%')
                            ->orWhere('delivery_address', 'like', '%' . $search . '%'))
                        ->orWhereHas('invoice', fn ($invoiceQuery) => $invoiceQuery
                            ->where('invoice_number', 'like', '%' . $search . '%')
                            ->orWhere('cufe', 'like', '%' . $search . '%'));
                });
            })
            ->when($filters['document_type'] ?? null, fn ($query, string $documentType) => $query->whereHas('invoice', fn ($invoiceQuery) => $invoiceQuery->where('invoice_type', $documentType)))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->whereHas('invoice', fn ($invoiceQuery) => $invoiceQuery->where('status', $status)))
            ->when($filters['payment_status'] ?? null, fn ($query, string $paymentStatus) => $query->where('payment_status', $paymentStatus))
            ->latest();

        $sales = $salesQuery
            ->paginate(15)
            ->withQueryString();

        $summaryBaseQuery = Sale::query()
            ->where('status', '<>', 'voided')
            ->whereDoesntHave('invoice', fn ($invoiceQuery) => $invoiceQuery->where('status', 'voided'));

        return view('billing.history', [
            'sales' => $sales,
            'filters' => $filters,
            'summary' => [
                'sales' => (clone $summaryBaseQuery)->count(),
                'today' => (clone $summaryBaseQuery)->whereDate('created_at', today())->count(),
                'revenue' => (float) (clone $summaryBaseQuery)->sum('total'),
                'electronic' => Invoice::query()
                    ->where('invoice_type', Invoice::TYPE_ELECTRONIC)
                    ->where('status', '<>', 'voided')
                    ->count(),
                'credit' => (float) CustomerCredit::query()
                    ->where('status', 'pending')
                    ->sum('balance'),
            ],
            'paymentMethods' => $this->paymentMethods(),
        ]);
    }

    public function voidedHistory(Request $request)
    {
        if (! Auth::user()?->hasRole('Admin')) {
            return response()->view('errors.403', [], 403);
        }

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $salesQuery = Sale::query()
            ->with(['user', 'box', 'invoice', 'payments.paymentMethod', 'tableOrder.table', 'customer', 'delivery', 'voidedBy'])
            ->withCount('items')
            ->where(function ($query): void {
                $query->where('status', 'voided')
                    ->orWhereHas('invoice', fn ($invoiceQuery) => $invoiceQuery->where('status', 'voided'));
            })
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($nestedQuery) use ($search) {
                    $nestedQuery
                        ->where('customer_name', 'like', '%' . $search . '%')
                        ->orWhere('id', 'like', '%' . $search . '%')
                        ->orWhere('void_reason', 'like', '%' . $search . '%')
                        ->orWhereHas('invoice', fn ($invoiceQuery) => $invoiceQuery
                            ->where('invoice_number', 'like', '%' . $search . '%')
                            ->orWhere('cufe', 'like', '%' . $search . '%'));
                });
            })
            ->latest('voided_at');

        return view('billing.voided', [
            'sales' => $salesQuery->paginate(15)->withQueryString(),
            'filters' => $filters,
            'summary' => [
                'voided' => (clone $salesQuery)->count(),
                'today' => (clone $salesQuery)->whereDate('voided_at', today())->count(),
                'total' => (float) (clone $salesQuery)->sum('total'),
            ],
        ]);
    }

    public function voidSale(Request $request, Sale $sale)
    {
        $user = Auth::user();

        if (! $user?->hasRole('Admin')) {
            return response()->view('errors.403', [], 403);
        }

        $validated = $request->validate([
            'void_reason' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($sale, $validated, $user): void {
            $currentSale = Sale::query()
                ->with(['invoice', 'payments', 'boxMovements.session', 'customerCredit', 'customerBalanceMovements.customer'])
                ->lockForUpdate()
                ->findOrFail($sale->id);

            if ($currentSale->isVoided()) {
                throw ValidationException::withMessages([
                    'void_reason' => 'Esta factura ya fue anulada.',
                ]);
            }

            $reason = $validated['void_reason'];
            $voidedAt = now();

            $this->restoreConsumedCustomerBalance($currentSale, $user->id, $reason);

            if ($currentSale->customerCredit) {
                $currentSale->customerCredit->update([
                    'balance' => 0,
                    'status' => 'voided',
                    'paid_reference' => 'Anulada: ' . $reason,
                    'paid_at' => $voidedAt,
                ]);
            }

            $currentSale->payments()->update([
                'status' => 'voided',
            ]);

            $currentSale->boxMovements->each(function (BoxMovement $movement) use ($currentSale, $user, $reason, $voidedAt): void {
                $originalAmount = money_value((float) $movement->amount);
                $description = trim((string) $movement->description);

                $movement->update([
                    'amount' => 0,
                    'balance_after' => $movement->balance_before,
                    'description' => ($description ? $description . ' | ' : '') . 'ANULADA: ' . $reason,
                ]);

                if ($movement->box_id && $movement->box_session_id) {
                    BoxAuditLog::query()->create([
                        'box_id' => $movement->box_id,
                        'box_session_id' => $movement->box_session_id,
                        'user_id' => $user->id,
                        'action' => 'sale_voided',
                        'description' => 'Anulacion de venta #' . $currentSale->id . ': ' . $reason,
                        'metadata' => [
                            'sale_id' => $currentSale->id,
                            'movement_id' => $movement->id,
                            'original_amount' => $originalAmount,
                            'voided_at' => $voidedAt->toDateTimeString(),
                        ],
                        'occurred_at' => $voidedAt,
                    ]);
                }
            });

            $currentSale->update([
                'status' => 'voided',
                'payment_status' => 'voided',
                'voided_by_user_id' => $user->id,
                'voided_at' => $voidedAt,
                'void_reason' => $reason,
            ]);

            if ($currentSale->invoice) {
                $currentSale->invoice->update([
                    'status' => 'voided',
                    'status_message' => 'Factura anulada por administrador: ' . $reason,
                    'voided_by_user_id' => $user->id,
                    'voided_at' => $voidedAt,
                    'void_reason' => $reason,
                ]);
            }
        });

        return redirect()
            ->route('billing.voided')
            ->with('success', 'Factura anulada correctamente. Ya no suma en caja ni en cierres.');
    }

    public function payCredit(Request $request, Sale $sale)
    {
        if ($response = $this->denyIfUnauthorized(['billing.charge'])) {
            return $response;
        }

        if ($sale->payment_status !== 'credit') {
            return redirect()
                ->route('billing.history')
                ->with('info', 'Esta venta no tiene credito pendiente.');
        }

        $remainingBalance = (float) ($sale->customerCredit?->balance ?? $sale->total);

        $validated = $request->validate([
            'payment_method_id' => ['nullable', $this->allowedPaymentMethodRule()],
            'amount_received' => ['required', 'numeric', 'gt:0', 'max:' . $remainingBalance],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $paymentMethod = null;

        if (! empty($validated['payment_method_id'])) {
            $paymentMethod = PaymentMethod::query()
                ->systemAllowed()
                ->whereKey($validated['payment_method_id'])
                ->first();
        }

        $userId = Auth::id();

        DB::transaction(function () use ($sale, $validated, $paymentMethod, $userId): void {
            $currentSale = Sale::query()
                ->with(['customerCredit', 'payments', 'customer'])
                ->lockForUpdate()
                ->findOrFail($sale->id);

            $currentCredit = $currentSale->customerCredit;
            $remainingBalance = money_value($currentCredit?->balance ?? $currentSale->total);
            $appliedAmount = money_value($validated['amount_received']);

            if ($appliedAmount <= 0 || $appliedAmount > $remainingBalance) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'amount_received' => 'El abono debe ser mayor a cero y no puede superar el saldo pendiente.',
                ]);
            }

            $box = Box::query()
                ->where('status', 'open')
                ->where('user_id', $userId)
                ->orderByDesc('opened_at')
                ->lockForUpdate()
                ->first();

            if (! $box) {
                $box = Box::query()
                    ->where('status', 'open')
                    ->orderByDesc('opened_at')
                    ->lockForUpdate()
                    ->first();
            }

            if (! $box) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'amount_received' => 'No hay una caja abierta para registrar el pago del credito.',
                ]);
            }

            $boxSession = BoxSession::query()
                ->where('box_id', $box->id)
                ->where('status', 'open')
                ->orderByDesc('opened_at')
                ->lockForUpdate()
                ->first();

            if (! $boxSession) {
                $boxSession = BoxSession::query()->create([
                    'box_id' => $box->id,
                    'user_id' => $box->user_id ?? $userId,
                    'opening_balance' => (float) $box->opening_balance,
                    'status' => 'open',
                    'opened_at' => $box->opened_at ?? now(),
                ]);
            }

            $payment = $currentSale->payments->first();
            $newRemainingBalance = money_value(max(0, $remainingBalance - $appliedAmount));
            $totalReceived = money_value((float) ($payment?->received_amount ?? 0) + $appliedAmount);
            $paymentStatus = $newRemainingBalance <= 0 ? 'completed' : 'pending';

            if ($payment) {
                $payment->update([
                    'payment_method_id' => $paymentMethod?->id,
                    'received_amount' => $totalReceived,
                    'change_amount' => 0,
                    'reference' => $validated['reference'] ?? $payment->reference,
                    'status' => $paymentStatus,
                ]);
            } else {
                $payment = $currentSale->payments()->create([
                    'payment_method_id' => $paymentMethod?->id,
                    'amount' => $currentSale->total,
                    'received_amount' => $appliedAmount,
                    'change_amount' => 0,
                    'tip_amount' => 0,
                    'reference' => $validated['reference'] ?? null,
                    'status' => $paymentStatus,
                ]);
            }

            $movementTotal = (float) BoxMovement::query()
                ->where('box_session_id', $boxSession->id)
                ->lockForUpdate()
                ->sum('amount');
            $balanceBefore = money_value((float) $box->opening_balance + $movementTotal);
            $boxImpact = $this->boxImpactAmount($appliedAmount, $paymentMethod);
            $balanceAfter = money_value($balanceBefore + $boxImpact);
            $description = ($newRemainingBalance <= 0 ? 'Pago final de credito #' : 'Abono a credito #') . $currentSale->id
                . ' | Cliente ' . ($currentSale->customer?->name ?: $currentSale->customer_name ?: 'Sin cliente')
                . ' | Metodo ' . ($paymentMethod?->name ?? 'Efectivo')
                . ' | ' . ($boxImpact > 0 ? 'Impacto en caja $' . money($boxImpact) : 'Sin impacto en caja');

            $movement = $box->movements()->create([
                'box_session_id' => $boxSession->id,
                'sale_id' => $currentSale->id,
                'payment_id' => $payment->id,
                'user_id' => $userId,
                'movement_type' => 'credit_payment',
                'amount' => $boxImpact,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $description,
                'occurred_at' => now(),
            ]);

            BoxAuditLog::query()->create([
                'box_id' => $box->id,
                'box_session_id' => $boxSession->id,
                'user_id' => $userId,
                'action' => 'credit_payment',
                'description' => $description,
                'metadata' => [
                    'movement_id' => $movement->id,
                    'sale_id' => $currentSale->id,
                    'payment_id' => $payment->id,
                    'amount' => $appliedAmount,
                    'box_impact' => $boxImpact,
                    'payment_method_id' => $paymentMethod?->id,
                    'remaining_balance' => $newRemainingBalance,
                ],
                'occurred_at' => now(),
            ]);

            if ($currentCredit) {
                $currentCredit->update([
                    'balance' => $newRemainingBalance,
                    'status' => $newRemainingBalance <= 0 ? 'paid' : 'pending',
                    'payment_method_id' => $paymentMethod?->id,
                    'paid_reference' => $validated['reference'] ?? null,
                    'paid_at' => $newRemainingBalance <= 0 ? now() : null,
                ]);
            }

            $currentSale->update([
                'status' => $newRemainingBalance <= 0 ? 'completed' : 'credit',
                'payment_status' => $newRemainingBalance <= 0 ? 'paid' : 'credit',
                'credit_due_at' => $newRemainingBalance <= 0 ? null : $currentSale->credit_due_at,
            ]);
        });

        return ($request->boolean('redirect_back')
            ? redirect()->back()
            : redirect()->route('billing.history', ['payment_status' => 'credit']))
            ->with('success', $remainingBalance > (float) $validated['amount_received']
                ? 'Abono registrado correctamente.'
                : 'Credito marcado como pagado correctamente.');
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
            ->systemAllowed()
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    private function allowedPaymentMethodRule()
    {
        return Rule::exists('payment_methods', 'id')
            ->where('active', true)
            ->whereIn('code', PaymentMethod::SYSTEM_ALLOWED_CODES);
    }

    private function isCashPaymentMethod(?PaymentMethod $paymentMethod): bool
    {
        if (! $paymentMethod) {
            return true;
        }

        return strtoupper((string) $paymentMethod->code) === 'CASH';
    }

    private function boxImpactAmount(float $amountReceived, ?PaymentMethod $paymentMethod): float
    {
        if (! $this->isCashPaymentMethod($paymentMethod)) {
            return 0.0;
        }

        return money_value($amountReceived);
    }

    private function restoreConsumedCustomerBalance(Sale $sale, int $userId, string $reason): void
    {
        $movements = $sale->customerBalanceMovements
            ->where('movement_type', 'sale_consumption')
            ->filter(fn (CustomerBalanceMovement $movement) => (float) $movement->amount < 0);

        foreach ($movements as $movement) {
            $customer = $movement->customer;

            if (! $customer) {
                continue;
            }

            $restoreAmount = money_value(abs((float) $movement->amount));
            $balanceBefore = money_value((float) $customer->available_balance);
            $balanceAfter = money_value($balanceBefore + $restoreAmount);

            $customer->update([
                'available_balance' => $balanceAfter,
            ]);

            CustomerBalanceMovement::query()->create([
                'customer_id' => $customer->id,
                'sale_id' => $sale->id,
                'created_by_user_id' => $userId,
                'movement_type' => 'customer_payment',
                'description' => 'Reversion de saldo por anulacion de factura: ' . $reason,
                'amount' => $restoreAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
            ]);
        }
    }

    private function assertSaleCanBeEdited(Sale $sale): void
    {
        if ($sale->isVoided() || $sale->invoice?->isVoided()) {
            throw ValidationException::withMessages([
                'items' => 'No se puede editar una factura anulada.',
            ]);
        }
    }

    private function canAdjustSale(Sale $sale): bool
    {
        return $sale->canBeEditedInOpenCashSession();
    }

    private function editableProductsForSale(Sale $sale): Collection
    {
        $currentProductIds = $sale->items
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

    private function normalizedSaleRows(array $items): Collection
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

    private function adjustSalePaymentAndBox(Sale $sale, float $delta): void
    {
        $payment = $sale->payments->first();

        if (! $payment) {
            return;
        }

        $paymentMethodCode = strtoupper((string) ($payment->paymentMethod?->code ?? ''));
        $movementImpact = money_value((float) $sale->boxMovements->sum('amount'));
        $affectsCash = $paymentMethodCode === 'CASH' || ($paymentMethodCode === '' && abs($movementImpact) > 0.009);

        $payment->update([
            'amount' => $sale->total,
            'received_amount' => $sale->payment_status === 'paid'
                ? money_value(max(0, (float) $payment->received_amount + $delta))
                : $payment->received_amount,
        ]);

        if ($sale->payment_status !== 'paid') {
            return;
        }

        $movement = $sale->boxMovements
            ->whereIn('movement_type', ['manual_payment', 'delivery_payment', 'sale_income'])
            ->first();

        if (! $movement) {
            return;
        }

        $boxImpactDelta = $affectsCash ? money_value($delta) : 0.0;
        $newAmount = money_value((float) $movement->amount + $boxImpactDelta);

        $movement->update([
            'amount' => $newAmount,
            'balance_after' => money_value((float) $movement->balance_before + $newAmount),
            'description' => trim((string) $movement->description) . ' | Ajustado por edicion de factura $' . money($delta),
        ]);

        if ($movement->box_id && $movement->box_session_id) {
            BoxAuditLog::query()->create([
                'box_id' => $movement->box_id,
                'box_session_id' => $movement->box_session_id,
                'user_id' => Auth::id(),
                'action' => 'sale_edited',
                'description' => 'Ajuste de factura #' . $sale->id . ' por $' . money($delta),
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

        if ($user && ($user->hasRole('Admin') || $user->hasRole('Cajero') || $user->hasAnyPermission($permissions))) {
            return null;
        }

        return response()->view('errors.403', [], 403);
    }
}
