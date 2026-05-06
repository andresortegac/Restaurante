<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Sale;
use App\Services\Factus\ElectronicInvoiceService;
use Illuminate\Validation\ValidationException;

class SaleDocumentService
{
    public function __construct(
        private readonly ElectronicInvoiceService $electronicInvoiceService
    ) {
    }

    public function issueDocumentForSale(Sale $sale, string $documentType, bool $sendElectronicImmediately = true): Invoice
    {
        return match ($documentType) {
            Invoice::TYPE_ELECTRONIC => $this->issueElectronicInvoiceForSale($sale, $sendElectronicImmediately),
            default => $this->issueTicketForSale($sale),
        };
    }

    public function issueTicketForSale(Sale $sale): Invoice
    {
        $sale->loadMissing('invoice');

        $invoice = $sale->invoice ?: new Invoice([
            'sale_id' => $sale->id,
        ]);

        if (! $invoice->exists) {
            $invoice->invoice_number = $invoice->generateInvoiceNumber('TKT');
        }

        $invoice->invoice_type = Invoice::TYPE_TICKET;
        $invoice->provider = 'local';
        $invoice->status = 'issued';
        $invoice->status_message = 'Ticket generado localmente.';
        $invoice->issued_at = $invoice->issued_at ?: now();
        $invoice->save();

        return $invoice->fresh();
    }

    public function issueElectronicInvoiceForSale(Sale $sale, bool $sendImmediately = true): Invoice
    {
        $sale->loadMissing(['invoice', 'customer', 'items.product.taxRate', 'payments.paymentMethod']);

        $this->assertElectronicInvoiceRequirements($sale->customer);

        $invoice = $this->electronicInvoiceService->issueForSale($sale, false, ! $sendImmediately);

        if ($sendImmediately && $this->electronicInvoiceService->settings()->is_enabled && ! $invoice->isSuccessful()) {
            $invoice = $this->electronicInvoiceService->send($invoice);
        }

        return $invoice->fresh();
    }

    public function electronicInvoiceStatus(?Customer $customer): array
    {
        $settings = $this->electronicInvoiceService->settings();

        if (! $settings->is_enabled) {
            return [
                'ready' => false,
                'message' => 'La facturación electrónica está deshabilitada en la configuración de Factus.',
                'missing_fields' => ['settings'],
            ];
        }

        if (! $customer) {
            return [
                'ready' => false,
                'message' => 'Debes vincular un cliente al pedido para emitir factura electrónica.',
                'missing_fields' => ['customer'],
            ];
        }

        $required = [
            'billing_identification' => $customer->billing_identification ?: $customer->document_number,
            'identification_document_code' => $customer->identification_document_code ?: $settings->default_identification_document_code,
            'legal_organization_code' => $customer->legal_organization_code ?: $settings->default_legal_organization_code,
            'tribute_code' => $customer->tribute_code ?: $settings->default_tribute_code,
            'municipality_code' => $customer->municipality_code ?: $settings->default_municipality_code,
            'billing_address' => $customer->billing_address,
            'email' => $customer->email,
            'phone' => $customer->phone,
        ];

        $missingFields = collect($required)
            ->filter(fn ($value) => blank($value))
            ->keys()
            ->values()
            ->all();

        return [
            'ready' => $missingFields === [],
            'message' => $missingFields === []
                ? 'El cliente tiene la información necesaria para factura electrónica.'
                : 'Faltan datos del cliente para facturación electrónica: ' . implode(', ', $missingFields) . '.',
            'missing_fields' => $missingFields,
        ];
    }

    public function assertElectronicInvoiceRequirements(?Customer $customer): void
    {
        $status = $this->electronicInvoiceStatus($customer);

        if ($status['ready']) {
            return;
        }

        throw ValidationException::withMessages([
            'document_type' => $status['message'],
        ]);
    }
}
