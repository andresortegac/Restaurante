<?php

namespace App\Http\Controllers;

use App\Models\Box;
use App\Models\BoxAuditLog;
use App\Models\BoxMovement;
use App\Models\BoxSession;
use App\Models\Customer;
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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
                        $query->orWhereKey($order->customer_id);
                    }
                })
                ->orderBy('name')
                ->limit(200)
                ->get(['id', 'name', 'document_number', 'billing_identification', 'email', 'phone']),
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
            'payment_method_id' => ['nullable', 'exists:payment_methods,id'],
            'amount_received' => ['required', 'numeric', 'min:0'],
            'document_type' => ['nullable', 'in:ticket,electronic'],
            'is_credit' => ['nullable', 'boolean'],
        ]);

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
                ->get(['id', 'name', 'document_number', 'billing_identification', 'email', 'phone', 'billing_address']),
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
            'payment_method_id' => ['nullable', 'exists:payment_methods,id'],
            'amount_received' => ['required', 'numeric', 'min:0'],
            'tip_amount' => ['nullable', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:255'],
            'is_credit' => ['nullable', 'boolean'],
            'credit_due_at' => ['nullable', 'date', 'after_or_equal:today'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0.01'],
        ]);

        $result = $this->manualBillingService->checkout($validated, Auth::id());
        $sale = $result['sale'];
        $invoice = $result['invoice'];
        $documentWarning = $result['document_warning'];

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $documentWarning
                    ? 'Cobro manual registrado, pero el documento quedo con novedad: ' . $documentWarning
                    : 'Cobro manual registrado correctamente.',
                'printUrl' => route('pos.sales.print', $sale),
                'redirectUrl' => route('billing.history'),
                'invoiceStatus' => $invoice?->status,
                'cufe' => $invoice?->cufe,
            ]);
        }

        session()->flash(
            $documentWarning ? 'warning' : 'success',
            $documentWarning
                ? 'Cobro manual registrado, pero el documento quedo con novedad: ' . $documentWarning
                : 'Cobro manual registrado correctamente.'
        );

        return view('orders.print-bridge', [
            'title' => 'Preparando documento',
            'message' => 'Estamos abriendo el documento y en unos segundos volveras al historial de facturacion.',
            'primaryActionLabel' => 'Abrir documento',
            'secondaryActionLabel' => 'Ir a facturacion',
            'redirectUrl' => route('billing.history'),
            'printUrl' => route('pos.sales.print', $sale),
        ]);
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
            ->with(['user', 'box', 'invoice', 'payments.paymentMethod', 'tableOrder.table', 'customer', 'delivery', 'customerCredit'])
            ->withCount('items')
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

        $summaryBaseQuery = Sale::query();

        return view('billing.history', [
            'sales' => $sales,
            'filters' => $filters,
            'summary' => [
                'sales' => (clone $summaryBaseQuery)->count(),
                'today' => (clone $summaryBaseQuery)->whereDate('created_at', today())->count(),
                'revenue' => (float) (clone $summaryBaseQuery)->sum('total'),
                'electronic' => Invoice::query()
                    ->where('invoice_type', Invoice::TYPE_ELECTRONIC)
                    ->count(),
                'credit' => (float) \App\Models\CustomerCredit::query()
                    ->where('status', 'pending')
                    ->sum('balance'),
            ],
            'paymentMethods' => $this->paymentMethods(),
        ]);
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
            'payment_method_id' => ['nullable', 'exists:payment_methods,id'],
            'amount_received' => ['required', 'numeric', 'gt:0', 'max:' . $remainingBalance],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $paymentMethod = null;

        if (! empty($validated['payment_method_id'])) {
            $paymentMethod = PaymentMethod::query()
                ->whereKey($validated['payment_method_id'])
                ->where('active', true)
                ->first();
        }

        $userId = Auth::id();

        DB::transaction(function () use ($sale, $validated, $paymentMethod, $userId): void {
            $currentSale = Sale::query()
                ->with(['customerCredit', 'payments', 'customer'])
                ->lockForUpdate()
                ->findOrFail($sale->id);

            $currentCredit = $currentSale->customerCredit;
            $remainingBalance = round((float) ($currentCredit?->balance ?? $currentSale->total), 2);
            $appliedAmount = round((float) $validated['amount_received'], 2);

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
            $newRemainingBalance = round(max(0, $remainingBalance - $appliedAmount), 2);
            $totalReceived = round((float) ($payment?->received_amount ?? 0) + $appliedAmount, 2);
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
            $balanceBefore = round((float) $box->opening_balance + $movementTotal, 2);
            $balanceAfter = round($balanceBefore + $appliedAmount, 2);
            $description = ($newRemainingBalance <= 0 ? 'Pago final de credito #' : 'Abono a credito #') . $currentSale->id
                . ' | Cliente ' . ($currentSale->customer?->name ?: $currentSale->customer_name ?: 'Sin cliente');

            $box->movements()->create([
                'box_session_id' => $boxSession->id,
                'sale_id' => $currentSale->id,
                'payment_id' => $payment->id,
                'user_id' => $userId,
                'movement_type' => 'credit_payment',
                'amount' => $appliedAmount,
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
                    'sale_id' => $currentSale->id,
                    'payment_id' => $payment->id,
                    'amount' => $appliedAmount,
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
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
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
