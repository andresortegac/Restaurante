<?php

namespace App\Http\Controllers;

use App\Models\BoxMovement;
use App\Models\Delivery;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportManagementController extends Controller
{
    public function index(Request $request): View|Response
    {
        if ($response = $this->denyIfUnauthorized('reports.view')) {
            return $response;
        }

        [$filters, $start, $end] = $this->validatedFilters($request);

        $salesQuery = $this->salesQuery($filters, $start, $end);
        $sales = (clone $salesQuery)
            ->with(['user', 'customer', 'invoice', 'payments.paymentMethod', 'tableOrder.table'])
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        $summaryRows = (clone $salesQuery)
            ->get(['id', 'subtotal', 'discount_amount', 'tax_amount', 'total']);
        $saleIds = $summaryRows->pluck('id');

        $cashMovements = $this->boxMovementsQuery($filters, $start, $end)->get(['amount']);
        $invoiceBreakdown = $this->invoiceQuery($filters, $start, $end)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $deliveryBreakdown = $this->deliveryQuery($filters, $start, $end)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('reports.index', [
            'filters' => $filters,
            'dateRange' => [
                'start' => $start,
                'end' => $end,
            ],
            'sales' => $sales,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'paymentMethods' => PaymentMethod::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
            'invoiceStatuses' => ['validated', 'submitted', 'pending', 'issued', 'failed', 'rejected'],
            'summary' => [
                'sales_count' => $summaryRows->count(),
                'gross_sales' => round((float) $summaryRows->sum('subtotal'), 2),
                'discounts' => round((float) $summaryRows->sum('discount_amount'), 2),
                'taxes' => round((float) $summaryRows->sum('tax_amount'), 2),
                'net_revenue' => round((float) $summaryRows->sum('total'), 2),
                'average_ticket' => round($summaryRows->count() > 0 ? (float) $summaryRows->avg('total') : 0, 2),
                'cash_income' => round((float) $cashMovements->where('amount', '>', 0)->sum('amount'), 2),
                'cash_expense' => round(abs((float) $cashMovements->where('amount', '<', 0)->sum('amount')), 2),
                'electronic_invoices' => (int) $invoiceBreakdown->sum(),
                'invoice_success' => (int) (($invoiceBreakdown['validated'] ?? 0) + ($invoiceBreakdown['submitted'] ?? 0)),
                'invoice_pending' => (int) (($invoiceBreakdown['pending'] ?? 0) + ($invoiceBreakdown['issued'] ?? 0)),
                'invoice_failed' => (int) (($invoiceBreakdown['failed'] ?? 0) + ($invoiceBreakdown['rejected'] ?? 0)),
                'deliveries_total' => (int) $deliveryBreakdown->sum(),
                'deliveries_completed' => (int) ($deliveryBreakdown['delivered'] ?? 0),
                'deliveries_active' => (int) (($deliveryBreakdown['pending'] ?? 0) + ($deliveryBreakdown['assigned'] ?? 0) + ($deliveryBreakdown['in_transit'] ?? 0)),
                'customers_served' => (int) $this->countDistinctCustomers($saleIds),
            ],
            'statusBreakdown' => [
                'invoices' => $invoiceBreakdown,
                'deliveries' => $deliveryBreakdown,
            ],
        ]);
    }

    public function analytics(Request $request): View|Response
    {
        if ($response = $this->denyIfUnauthorized('reports.view')) {
            return $response;
        }

        [$filters, $start, $end] = $this->validatedFilters($request);

        $salesQuery = $this->salesQuery($filters, $start, $end);

        $salesByUser = (clone $salesQuery)
            ->selectRaw('user_id, COUNT(*) as sales_count, SUM(total) as revenue')
            ->with('user:id,name')
            ->groupBy('user_id')
            ->orderByDesc('revenue')
            ->get();

        $dailySales = (clone $salesQuery)
            ->selectRaw('DATE(created_at) as report_date, COUNT(*) as sales_count, SUM(total) as revenue')
            ->groupBy('report_date')
            ->orderBy('report_date')
            ->get();

        $paymentBreakdown = $this->paymentQuery($filters, $start, $end)
            ->selectRaw('payment_method_id, COUNT(*) as payments_count, SUM(amount) as total_amount')
            ->with('paymentMethod:id,name')
            ->groupBy('payment_method_id')
            ->orderByDesc('total_amount')
            ->get();

        $topProducts = $this->saleItemsQuery($filters, $start, $end)
            ->selectRaw('product_name, SUM(quantity) as quantity_sold, SUM(subtotal) as revenue')
            ->groupBy('product_name')
            ->orderByDesc('quantity_sold')
            ->limit(8)
            ->get();

        $invoiceBreakdown = $this->invoiceQuery($filters, $start, $end)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->orderByDesc('total')
            ->get();

        $deliveryBreakdown = $this->deliveryQuery($filters, $start, $end)
            ->selectRaw('status, COUNT(*) as total, SUM(total_charge) as billed_total')
            ->groupBy('status')
            ->orderByDesc('total')
            ->get();

        return view('reports.analytics', [
            'filters' => $filters,
            'dateRange' => [
                'start' => $start,
                'end' => $end,
            ],
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'paymentMethods' => PaymentMethod::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
            'salesByUser' => $salesByUser,
            'dailySales' => $dailySales,
            'paymentBreakdown' => $paymentBreakdown,
            'topProducts' => $topProducts,
            'invoiceBreakdown' => $invoiceBreakdown,
            'deliveryBreakdown' => $deliveryBreakdown,
        ]);
    }

    public function export(Request $request): StreamedResponse|Response
    {
        if ($response = $this->denyIfUnauthorized('reports.export')) {
            return $response;
        }

        [$filters, $start, $end] = $this->validatedFilters($request);

        $sales = $this->salesQuery($filters, $start, $end)
            ->with(['user', 'customer', 'invoice', 'payments.paymentMethod'])
            ->latest('created_at')
            ->get();

        return response()->streamDownload(function () use ($sales): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Fecha',
                'Venta',
                'Vendedor',
                'Cliente',
                'Metodo de pago',
                'Subtotal',
                'Descuento',
                'Impuesto',
                'Total',
                'Estado factura electronica',
            ]);

            foreach ($sales as $sale) {
                fputcsv($handle, [
                    $sale->created_at?->format('Y-m-d H:i:s'),
                    $sale->id,
                    $sale->user?->name ?? 'Sin usuario',
                    $sale->customer?->name ?? $sale->customer_name ?? 'Sin cliente',
                    $sale->payments->pluck('paymentMethod.name')->filter()->implode(', ') ?: 'Sin pago',
                    number_format((float) $sale->subtotal, 2, '.', ''),
                    number_format((float) $sale->discount_amount, 2, '.', ''),
                    number_format((float) $sale->tax_amount, 2, '.', ''),
                    number_format((float) $sale->total, 2, '.', ''),
                    $sale->invoice?->status ?? 'Sin factura',
                ]);
            }

            fclose($handle);
        }, 'reporte-ventas-' . now()->format('Ymd-His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function validatedFilters(Request $request): array
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'invoice_status' => ['nullable', 'string', 'max:50'],
        ]);

        $start = isset($filters['date_from'])
            ? Carbon::parse($filters['date_from'])->startOfDay()
            : now()->startOfMonth();
        $end = isset($filters['date_to'])
            ? Carbon::parse($filters['date_to'])->endOfDay()
            : now()->endOfDay();

        if ($end->lt($start)) {
            $end = (clone $start)->endOfDay();
            $filters['date_to'] = $end->toDateString();
        }

        $filters['date_from'] = $filters['date_from'] ?? $start->toDateString();
        $filters['date_to'] = $filters['date_to'] ?? $end->toDateString();

        return [$filters, $start, $end];
    }

    private function salesQuery(array $filters, Carbon $start, Carbon $end): Builder
    {
        return Sale::query()
            ->whereBetween('created_at', [$start, $end])
            ->when($filters['user_id'] ?? null, fn (Builder $query, int $userId) => $query->where('user_id', $userId))
            ->when($filters['payment_method_id'] ?? null, function (Builder $query, int $paymentMethodId) {
                $query->whereHas('payments', fn (Builder $paymentQuery) => $paymentQuery->where('payment_method_id', $paymentMethodId));
            })
            ->when($filters['invoice_status'] ?? null, function (Builder $query, string $invoiceStatus) {
                $query->whereHas('invoice', fn (Builder $invoiceQuery) => $invoiceQuery->where('status', $invoiceStatus));
            });
    }

    private function paymentQuery(array $filters, Carbon $start, Carbon $end): Builder
    {
        return Payment::query()
            ->whereHas('sale', function (Builder $query) use ($filters, $start, $end) {
                $this->applySaleFilters($query, $filters, $start, $end);
            });
    }

    private function invoiceQuery(array $filters, Carbon $start, Carbon $end): Builder
    {
        return Invoice::query()
            ->whereHas('sale', function (Builder $query) use ($filters, $start, $end) {
                $this->applySaleFilters($query, $filters, $start, $end);
            })
            ->when($filters['invoice_status'] ?? null, fn (Builder $query, string $invoiceStatus) => $query->where('status', $invoiceStatus));
    }

    private function saleItemsQuery(array $filters, Carbon $start, Carbon $end): Builder
    {
        return SaleItem::query()
            ->whereHas('sale', function (Builder $query) use ($filters, $start, $end) {
                $this->applySaleFilters($query, $filters, $start, $end);
            });
    }

    private function boxMovementsQuery(array $filters, Carbon $start, Carbon $end): Builder
    {
        return BoxMovement::query()
            ->whereBetween('occurred_at', [$start, $end])
            ->when($filters['user_id'] ?? null, fn (Builder $query, int $userId) => $query->where('user_id', $userId));
    }

    private function deliveryQuery(array $filters, Carbon $start, Carbon $end): Builder
    {
        return Delivery::query()
            ->whereBetween('created_at', [$start, $end])
            ->when($filters['user_id'] ?? null, fn (Builder $query, int $userId) => $query->where('assigned_user_id', $userId));
    }

    private function applySaleFilters(Builder $query, array $filters, Carbon $start, Carbon $end): void
    {
        $query->whereBetween('created_at', [$start, $end])
            ->when($filters['user_id'] ?? null, fn (Builder $saleQuery, int $userId) => $saleQuery->where('user_id', $userId))
            ->when($filters['payment_method_id'] ?? null, function (Builder $saleQuery, int $paymentMethodId) {
                $saleQuery->whereHas('payments', fn (Builder $paymentQuery) => $paymentQuery->where('payment_method_id', $paymentMethodId));
            });
    }

    private function countDistinctCustomers($saleIds): int
    {
        if ($saleIds->isEmpty()) {
            return 0;
        }

        return Sale::query()
            ->whereKey($saleIds)
            ->where(function (Builder $query) {
                $query->whereNotNull('customer_id')
                    ->orWhereNotNull('customer_name');
            })
            ->count();
    }

    private function denyIfUnauthorized(string $permission): ?Response
    {
        $user = auth()->user();

        if ($user && ($user->hasRole('Admin') || $user->hasPermission($permission))) {
            return null;
        }

        return response()->view('errors.403', [], 403);
    }
}
