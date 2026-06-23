<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\Factus\ElectronicInvoiceService;
use App\Services\Factus\FactusApiException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ElectronicInvoiceManagementController extends Controller
{
    public function __construct(
        private readonly ElectronicInvoiceService $service
    ) {
    }

    public function index(Request $request)
    {
        if ($response = $this->denyIfUnauthorized(['electronic_invoices.view', 'electronic_invoices.manage'])) {
            return $response;
        }

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $invoiceQuery = Invoice::query()
            ->with(['sale.customer', 'logs'])
            ->where('invoice_type', Invoice::TYPE_ELECTRONIC)
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($nestedQuery) use ($search) {
                    $nestedQuery
                        ->where('invoice_number', 'like', '%' . $search . '%')
                        ->orWhere('reference_code', 'like', '%' . $search . '%')
                        ->orWhere('electronic_number', 'like', '%' . $search . '%')
                        ->orWhere('cufe', 'like', '%' . $search . '%')
                        ->orWhereHas('sale', fn ($saleQuery) => $saleQuery
                            ->where('id', $search)
                            ->orWhere('customer_name', 'like', '%' . $search . '%')
                            ->orWhereHas('customer', fn ($customerQuery) => $customerQuery
                                ->where('name', 'like', '%' . $search . '%')
                                ->orWhere('document_number', 'like', '%' . $search . '%')
                                ->orWhere('email', 'like', '%' . $search . '%')));
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['date_from'] ?? null, fn ($query, string $dateFrom) => $query->whereDate('issued_at', '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn ($query, string $dateTo) => $query->whereDate('issued_at', '<=', $dateTo));

        $invoices = (clone $invoiceQuery)
            ->latest('issued_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('electronic-invoices.index', [
            'invoices' => $invoices,
            'filters' => $filters,
            'summary' => [
                'total' => (clone $invoiceQuery)->count(),
                'validated' => (clone $invoiceQuery)->where('status', 'validated')->count(),
                'failed' => (clone $invoiceQuery)->where('status', 'failed')->count(),
                'pending' => (clone $invoiceQuery)->whereIn('status', ['queued', 'submitting', 'submitted', 'draft'])->count(),
            ],
        ]);
    }

    public function show(Invoice $invoice)
    {
        if ($response = $this->denyIfUnauthorized(['electronic_invoices.view', 'electronic_invoices.manage'])) {
            return $response;
        }

        $invoice->load(['sale.items.product', 'sale.payments.paymentMethod', 'sale.customer', 'logs']);

        return view('electronic-invoices.show', [
            'invoice' => $invoice,
        ]);
    }

    public function retry(Invoice $invoice): RedirectResponse|Response
    {
        if ($response = $this->denyIfUnauthorized(['electronic_invoices.retry'])) {
            return $response;
        }

        $this->service->retry($invoice);

        return redirect()
            ->route('electronic-invoices.show', $invoice)
            ->with('success', 'Factura reenviada a la cola de procesamiento.');
    }

    public function sync(Invoice $invoice): RedirectResponse|Response
    {
        if ($response = $this->denyIfUnauthorized(['electronic_invoices.manage', 'electronic_invoices.retry'])) {
            return $response;
        }

        try {
            $this->service->syncStatus($invoice);
        } catch (FactusApiException $exception) {
            return redirect()
                ->route('electronic-invoices.show', $invoice)
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('electronic-invoices.show', $invoice)
            ->with('success', 'Estado de factura sincronizado correctamente.');
    }

    public function syncPending(): RedirectResponse|Response
    {
        if ($response = $this->denyIfUnauthorized(['electronic_invoices.manage', 'electronic_invoices.retry'])) {
            return $response;
        }

        $synced = 0;
        $failed = 0;

        Invoice::query()
            ->where('invoice_type', Invoice::TYPE_ELECTRONIC)
            ->whereNotNull('electronic_number')
            ->whereIn('status', ['submitted', 'submitting', 'queued', 'failed'])
            ->latest()
            ->limit(25)
            ->get()
            ->each(function (Invoice $invoice) use (&$synced, &$failed): void {
                try {
                    $this->service->syncStatus($invoice);
                    $synced++;
                } catch (\Throwable) {
                    $failed++;
                }
            });

        return redirect()
            ->route('electronic-invoices.index')
            ->with('success', 'Sincronizacion terminada. Actualizadas: ' . $synced . '. Con error: ' . $failed . '.');
    }

    public function downloadPdf(Invoice $invoice)
    {
        if ($response = $this->denyIfUnauthorized(['electronic_invoices.view', 'electronic_invoices.manage'])) {
            return $response;
        }

        if (!$invoice->pdf_path || !Storage::disk('local')->exists($invoice->pdf_path)) {
            try {
                $invoice = $this->service->ensureArtifacts($invoice);
            } catch (\Throwable) {
                abort(404);
            }
        }

        abort_if(!$invoice->pdf_path || !Storage::disk('local')->exists($invoice->pdf_path), 404);

        return Storage::disk('local')->download($invoice->pdf_path);
    }

    public function downloadXml(Invoice $invoice)
    {
        if ($response = $this->denyIfUnauthorized(['electronic_invoices.view', 'electronic_invoices.manage'])) {
            return $response;
        }

        if (!$invoice->xml_path || !Storage::disk('local')->exists($invoice->xml_path)) {
            try {
                $invoice = $this->service->ensureArtifacts($invoice);
            } catch (\Throwable) {
                abort(404);
            }
        }

        abort_if(!$invoice->xml_path || !Storage::disk('local')->exists($invoice->xml_path), 404);

        return Storage::disk('local')->download($invoice->xml_path);
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
