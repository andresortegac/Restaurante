<?php

namespace App\Services\Factus;

use App\Jobs\SendElectronicInvoiceJob;
use App\Models\ElectronicInvoiceLog;
use App\Models\ElectronicInvoiceSetting;
use App\Models\Invoice;
use App\Models\Sale;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ElectronicInvoiceService
{
    public function __construct(
        private readonly FactusApiClient $client,
        private readonly FactusInvoicePayloadBuilder $payloadBuilder
    ) {
    }

    public function issueForSale(Sale $sale, bool $forceRetry = false, bool $dispatchToQueue = true): Invoice
    {
        $settings = $this->settings();

        $invoice = $sale->invoice ?: new Invoice([
            'sale_id' => $sale->id,
            'invoice_type' => Invoice::TYPE_ELECTRONIC,
        ]);

        if (!$invoice->exists) {
            $invoice->invoice_number = $invoice->generateInvoiceNumber();
        }

        $invoice->provider = 'factus';
        $invoice->reference_code = $invoice->reference_code ?: $this->referenceCode($sale, $invoice);
        $invoice->status = $settings->is_enabled ? 'queued' : 'draft';
        $invoice->status_message = $settings->is_enabled
            ? 'Factura en cola para envío electrónico.'
            : 'Facturación electrónica deshabilitada.';
        $invoice->issued_at = $invoice->issued_at ?: now();
        $invoice->save();

        if (!$settings->is_enabled) {
            $this->log($invoice, 'warning', 'invoice.skipped', 'La facturación electrónica está deshabilitada.');

            return $invoice;
        }

        $invoice->factus_payload = $this->payloadBuilder->build($sale, $settings, $invoice->reference_code);
        $invoice->save();

        if ($dispatchToQueue && ($forceRetry || !$invoice->isSuccessful())) {
            SendElectronicInvoiceJob::dispatch($invoice->id)->afterCommit();
        }

        return $invoice;
    }

    public function send(Invoice $invoice): Invoice
    {
        $invoice->loadMissing(['sale.items.product.taxRate', 'sale.payments.paymentMethod', 'sale.customer']);
        $settings = $this->settings();

        try {
            $invoice->update([
                'status' => 'submitting',
                'status_message' => 'Enviando factura a Factus.',
                'last_attempt_at' => now(),
                'retry_count' => $invoice->retry_count + 1,
            ]);

            $payload = $invoice->factus_payload ?: $this->payloadBuilder->build($invoice->sale, $settings, $invoice->reference_code ?: $this->referenceCode($invoice->sale, $invoice));
            $response = $this->client->createBill($settings, $payload);

            $this->syncInvoiceFromFactusResponse($invoice, $response);
            $this->downloadArtifacts($invoice, $settings);

            $this->log($invoice, 'info', 'invoice.sent', 'Factura electrónica enviada correctamente a Factus.', [
                'number' => $invoice->electronic_number,
                'cufe' => $invoice->cufe,
            ]);
        } catch (Throwable $exception) {
            $context = $exception instanceof FactusApiException ? $exception->context() : [];

            $invoice->update([
                'status' => 'failed',
                'status_message' => $exception->getMessage(),
                'validation_errors' => $context['response']['errors'] ?? $context['response'] ?? $context,
                'last_error_at' => now(),
            ]);

            $this->log($invoice, 'error', 'invoice.failed', $exception->getMessage(), $context);

            throw $exception;
        }

        return $invoice->fresh();
    }

    public function syncStatus(Invoice $invoice): Invoice
    {
        $settings = $this->settings();

        if (!$invoice->electronic_number) {
            throw new FactusApiException('La factura no tiene número electrónico asignado en Factus.');
        }

        $response = $this->client->getBill($settings, $invoice->electronic_number);
        $this->syncInvoiceFromFactusResponse($invoice, $response);
        $this->downloadArtifacts($invoice, $settings);

        $this->log($invoice, 'info', 'invoice.synced', 'Estado de factura sincronizado desde Factus.');

        return $invoice->fresh();
    }

    public function ensureArtifacts(Invoice $invoice): Invoice
    {
        $settings = $this->settings();

        if (!$invoice->electronic_number) {
            throw new FactusApiException('La factura no tiene número electrónico asignado en Factus.');
        }

        $this->downloadArtifacts($invoice, $settings);

        return $invoice->fresh();
    }

    public function testConnection(): array
    {
        $settings = $this->settings();
        $auth = $this->client->authenticate($settings);
        $ranges = $this->numberingRanges();

        return [
            'token_type' => $auth['token_type'] ?? 'Bearer',
            'expires_in' => $auth['expires_in'] ?? null,
            'ranges' => $ranges,
        ];
    }

    public function retry(Invoice $invoice): Invoice
    {
        $invoice->update([
            'status' => 'queued',
            'status_message' => 'Factura reencolada para reintento.',
        ]);

        SendElectronicInvoiceJob::dispatch($invoice->id);

        $this->log($invoice, 'warning', 'invoice.retry', 'Factura enviada nuevamente a la cola de procesamiento.');

        return $invoice->fresh();
    }

    public function storeSettings(array $validated): ElectronicInvoiceSetting
    {
        $settings = $this->settings();
        $settings->fill($validated);
        $settings->save();

        return $settings;
    }

    public function settings(): ElectronicInvoiceSetting
    {
        $settings = ElectronicInvoiceSetting::query()->firstOrNew();
        $settings->fill($this->configuredDefaults());

        if (!$settings->exists || $settings->isDirty()) {
            $settings->save();
        }

        return $settings;
    }

    public function numberingRanges(): array
    {
        $settings = $this->settings();

        if (!$settings->client_id || !$settings->client_secret || !$settings->username || !$settings->password) {
            return [];
        }

        $response = $this->client->listNumberingRanges($settings, ['filter[is_active]' => 1, 'filter[document]' => $settings->document_code ?: '01']);

        return $response['data'] ?? [];
    }

    private function syncInvoiceFromFactusResponse(Invoice $invoice, array $response): void
    {
        $data = $response['data'] ?? [];
        $isValidated = (bool) ($data['is_validated'] ?? false);

        $invoice->update([
            'status' => $isValidated ? 'validated' : 'submitted',
            'status_message' => $response['message'] ?? 'Factura procesada por Factus.',
            'factus_response' => $response,
            'validation_errors' => $data['errors'] ?? null,
            'electronic_number' => $data['number'] ?? $invoice->electronic_number,
            'cufe' => $data['cufe'] ?? $invoice->cufe,
            'public_url' => $data['public_url'] ?? $invoice->public_url,
            'qr_url' => $data['qr'] ?? $invoice->qr_url,
            'sent_at' => $invoice->sent_at ?: now(),
            'synced_at' => now(),
            'last_error_at' => null,
        ]);
    }

    private function downloadArtifacts(Invoice $invoice, ElectronicInvoiceSetting $settings): void
    {
        if (!$invoice->electronic_number) {
            return;
        }

        $pdfResponse = $this->client->downloadPdf($settings, $invoice->electronic_number);
        $xmlResponse = $this->client->downloadXml($settings, $invoice->electronic_number);

        $pdfData = $pdfResponse['data'] ?? [];
        $xmlData = $xmlResponse['data'] ?? [];

        if (!empty($pdfData['pdf_base_64_encoded'])) {
            $pdfPath = 'electronic-invoices/' . $invoice->id . '/' . ($pdfData['file_name'] ?? 'invoice') . '.pdf';
            Storage::disk('local')->put($pdfPath, base64_decode($pdfData['pdf_base_64_encoded']));
            $invoice->pdf_path = $pdfPath;
        }

        if (!empty($xmlData['xml_base_64_encoded'])) {
            $xmlPath = 'electronic-invoices/' . $invoice->id . '/' . ($xmlData['file_name'] ?? 'invoice') . '.xml';
            Storage::disk('local')->put($xmlPath, base64_decode($xmlData['xml_base_64_encoded']));
            $invoice->xml_path = $xmlPath;
        }

        $invoice->save();
    }

    private function referenceCode(Sale $sale, Invoice $invoice): string
    {
        return 'SALE-' . now()->format('Ymd') . '-' . $sale->id . '-' . ($invoice->id ?: 'draft');
    }

    private function configuredDefaults(): array
    {
        return [
            'is_enabled' => (bool) config('factus.enabled'),
            'environment' => config('factus.environment', 'sandbox'),
            'client_id' => config('factus.client_id'),
            'client_secret' => config('factus.client_secret'),
            'username' => config('factus.username'),
            'password' => config('factus.password'),
            'numbering_range_id' => filled(config('factus.numbering_range_id')) ? (int) config('factus.numbering_range_id') : null,
            'document_code' => config('factus.document_code', '01'),
            'operation_type' => config('factus.operation_type', '10'),
            'send_email' => (bool) config('factus.send_email', true),
            'default_identification_document_code' => config('factus.default_identification_document_code', '13'),
            'default_legal_organization_code' => config('factus.default_legal_organization_code', '2'),
            'default_tribute_code' => config('factus.default_tribute_code', 'ZZ'),
            'default_municipality_code' => config('factus.default_municipality_code'),
            'default_unit_measure_code' => config('factus.default_unit_measure_code', '94'),
            'default_standard_code' => config('factus.default_standard_code', '999'),
        ];
    }

    private function log(Invoice $invoice, string $level, string $event, string $message, array $context = []): void
    {
        ElectronicInvoiceLog::query()->create([
            'invoice_id' => $invoice->id,
            'level' => $level,
            'event' => $event,
            'message' => $message,
            'context' => $context,
        ]);
    }
}
