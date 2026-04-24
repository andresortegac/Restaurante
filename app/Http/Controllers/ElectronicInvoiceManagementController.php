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
        ]);

        $invoices = Invoice::query()
            ->with(['sale.customer', 'logs'])
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($nestedQuery) use ($search) {
                    $nestedQuery
                        ->where('invoice_number', 'like', '%' . $search . '%')
                        ->orWhere('reference_code', 'like', '%' . $search . '%')
                        ->orWhere('electronic_number', 'like', '%' . $search . '%')
                        ->orWhereHas('sale', fn ($saleQuery) => $saleQuery->where('customer_name', 'like', '%' . $search . '%'));
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('electronic-invoices.index', [
            'invoices' => $invoices,
            'filters' => $filters,
            'summary' => [
                'total' => Invoice::count(),
                'validated' => Invoice::where('status', 'validated')->count(),
                'failed' => Invoice::where('status', 'failed')->count(),
                'pending' => Invoice::whereIn('status', ['queued', 'submitting', 'submitted', 'draft'])->count(),
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

    public function settings()
    {
        if ($response = $this->denyIfUnauthorized(['electronic_invoices.settings'])) {
            return $response;
        }

        try {
            $numberingRanges = $this->service->numberingRanges();
        } catch (\Throwable) {
            $numberingRanges = [];
        }

        return view('electronic-invoices.settings', [
            'settings' => $this->service->settings(),
            'numberingRanges' => $numberingRanges,
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse|Response
    {
        if ($response = $this->denyIfUnauthorized(['electronic_invoices.settings'])) {
            return $response;
        }

        $validated = $request->validate([
            'is_enabled' => ['nullable', 'boolean'],
            'environment' => ['required', 'in:sandbox,production'],
            'client_id' => ['nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string'],
            'numbering_range_id' => ['nullable', 'integer'],
            'document_code' => ['required', 'string', 'max:10'],
            'operation_type' => ['required', 'string', 'max:10'],
            'send_email' => ['nullable', 'boolean'],
            'default_identification_document_code' => ['nullable', 'string', 'max:10'],
            'default_legal_organization_code' => ['nullable', 'string', 'max:10'],
            'default_tribute_code' => ['nullable', 'string', 'max:10'],
            'default_municipality_code' => ['nullable', 'string', 'max:20'],
            'default_unit_measure_code' => ['required', 'string', 'max:10'],
            'default_standard_code' => ['required', 'string', 'max:20'],
        ]);

        $validated['is_enabled'] = $request->boolean('is_enabled');
        $validated['send_email'] = $request->boolean('send_email');

        $settings = $this->service->settings();

        foreach (['client_secret', 'password'] as $sensitiveField) {
            if (blank($validated[$sensitiveField] ?? null)) {
                unset($validated[$sensitiveField]);
            }
        }

        foreach (['client_id', 'username'] as $plainField) {
            if (blank($validated[$plainField] ?? null) && $settings->{$plainField}) {
                unset($validated[$plainField]);
            }
        }

        $this->service->storeSettings($validated);

        return redirect()
            ->route('electronic-invoices.settings')
            ->with('success', 'Configuración de Factus actualizada correctamente.');
    }

    public function downloadPdf(Invoice $invoice)
    {
        if ($response = $this->denyIfUnauthorized(['electronic_invoices.view', 'electronic_invoices.manage'])) {
            return $response;
        }

        abort_unless($invoice->pdf_path && Storage::disk('local')->exists($invoice->pdf_path), 404);

        return Storage::disk('local')->download($invoice->pdf_path);
    }

    public function downloadXml(Invoice $invoice)
    {
        if ($response = $this->denyIfUnauthorized(['electronic_invoices.view', 'electronic_invoices.manage'])) {
            return $response;
        }

        abort_unless($invoice->xml_path && Storage::disk('local')->exists($invoice->xml_path), 404);

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
