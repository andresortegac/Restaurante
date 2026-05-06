<?php

namespace App\Http\Controllers;

use App\Models\Box;
use App\Models\BoxSession;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\TableOrder;
use App\Services\SaleDocumentService;
use App\Services\TableOrderBillingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class BillingManagementController extends Controller
{
    public function __construct(
        private readonly TableOrderBillingService $billingService,
        private readonly SaleDocumentService $saleDocumentService
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
            'splitSummary' => $order->splitSummary(),
            'activeBox' => $this->activeBox(),
            'paymentMethods' => $this->paymentMethods(),
            'billingReadiness' => $billingReadiness,
        ]);
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

    public function history(Request $request)
    {
        if ($response = $this->denyIfUnauthorized(['billing.view', 'billing.history'])) {
            return $response;
        }

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'document_type' => ['nullable', 'in:ticket,electronic'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        $salesQuery = Sale::query()
            ->with(['user', 'box', 'invoice', 'payments.paymentMethod', 'tableOrder.table', 'customer'])
            ->withCount('items')
            ->whereNotNull('table_order_id')
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($nestedQuery) use ($search) {
                    $nestedQuery
                        ->where('customer_name', 'like', '%' . $search . '%')
                        ->orWhereHas('tableOrder', fn ($orderQuery) => $orderQuery
                            ->where('order_number', 'like', '%' . $search . '%')
                            ->orWhereHas('table', fn ($tableQuery) => $tableQuery
                                ->where('name', 'like', '%' . $search . '%')
                                ->orWhere('code', 'like', '%' . $search . '%')))
                        ->orWhereHas('invoice', fn ($invoiceQuery) => $invoiceQuery
                            ->where('invoice_number', 'like', '%' . $search . '%')
                            ->orWhere('cufe', 'like', '%' . $search . '%'));
                });
            })
            ->when($filters['document_type'] ?? null, fn ($query, string $documentType) => $query->whereHas('invoice', fn ($invoiceQuery) => $invoiceQuery->where('invoice_type', $documentType)))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->whereHas('invoice', fn ($invoiceQuery) => $invoiceQuery->where('status', $status)))
            ->latest();

        $sales = $salesQuery
            ->paginate(15)
            ->withQueryString();

        $summaryBaseQuery = Sale::query()->whereNotNull('table_order_id');

        return view('billing.history', [
            'sales' => $sales,
            'filters' => $filters,
            'summary' => [
                'sales' => (clone $summaryBaseQuery)->count(),
                'today' => (clone $summaryBaseQuery)->whereDate('created_at', today())->count(),
                'revenue' => (float) (clone $summaryBaseQuery)->sum('total'),
                'electronic' => Invoice::query()
                    ->where('invoice_type', Invoice::TYPE_ELECTRONIC)
                    ->whereHas('sale', fn ($saleQuery) => $saleQuery->whereNotNull('table_order_id'))
                    ->count(),
            ],
        ]);
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
