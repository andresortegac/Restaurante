<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Sale;
use Illuminate\Http\Request;
use App\Services\SaleDocumentService;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly SaleDocumentService $saleDocumentService
    ) {
    }

    public function show($id)
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->load('sale.items.product', 'sale.payments.paymentMethod', 'sale.user', 'sale.box', 'sale.customer');
        $this->sanitizeInvoiceForDisplay($invoice);

        return response()->json($invoice);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sale_id' => 'required|exists:sales,id',
            'invoice_type' => 'required|in:factura,boleta,ticket,electronic',
        ]);

        $sale = Sale::findOrFail($validated['sale_id']);

        $invoice = $this->issueInvoice($sale, $validated['invoice_type']);

        return response()->json($invoice, 201);
    }

    public function printSale(Sale $sale)
    {
        $sale->load(['user', 'box', 'items.product', 'payments.paymentMethod', 'invoice', 'tableOrder.table', 'customer']);

        $invoice = $sale->invoice ?: $this->saleDocumentService->issueTicketForSale($sale);
        $sale->setRelation('invoice', $invoice);
        $this->sanitizeSaleForDisplay($sale);
        $this->sanitizeInvoiceForDisplay($invoice);

        return view('pos.invoices.print', [
            'sale' => $sale,
            'invoice' => $invoice,
        ]);
    }

    private function issueInvoice(Sale $sale, string $invoiceType): Invoice
    {
        $sale->loadMissing(['invoice', 'customer', 'items.product.taxRate', 'payments.paymentMethod']);

        $normalizedType = match ($invoiceType) {
            'factura', 'electronic' => Invoice::TYPE_ELECTRONIC,
            default => Invoice::TYPE_TICKET,
        };

        return $this->saleDocumentService->issueDocumentForSale($sale, $normalizedType);
    }

    private function sanitizeInvoiceForDisplay(Invoice $invoice): void
    {
        $invoice->invoice_number = $this->sanitizeString($invoice->invoice_number);
        $invoice->invoice_type = $this->sanitizeString($invoice->invoice_type);
        $invoice->status = $this->sanitizeString($invoice->status);

        if ($invoice->relationLoaded('sale') && $invoice->sale) {
            $this->sanitizeSaleForDisplay($invoice->sale);
        }
    }

    private function sanitizeSaleForDisplay(Sale $sale): void
    {
        $sale->status = $this->sanitizeString($sale->status);
        $sale->customer_name = $sale->customer_name === null ? null : $this->sanitizeString($sale->customer_name);
        $sale->notes = $sale->notes === null ? null : $this->sanitizeString($sale->notes);

        if ($sale->relationLoaded('customer') && $sale->customer) {
            $sale->customer->name = $this->sanitizeString($sale->customer->name);
        }

        if ($sale->relationLoaded('user') && $sale->user) {
            $sale->user->name = $this->sanitizeString($sale->user->name);
        }

        if ($sale->relationLoaded('box') && $sale->box) {
            $sale->box->name = $this->sanitizeString($sale->box->name);
            $sale->box->code = $this->sanitizeString($sale->box->code);
        }

        if ($sale->relationLoaded('items')) {
            foreach ($sale->items as $item) {
                $item->product_name = $item->product_name === null ? null : $this->sanitizeString($item->product_name);

                if ($item->relationLoaded('product') && $item->product) {
                    $item->product->name = $this->sanitizeString($item->product->name);
                    $item->product->category = $item->product->category === null ? null : $this->sanitizeString($item->product->category);
                }
            }
        }

        if ($sale->relationLoaded('tableOrder') && $sale->tableOrder) {
            $sale->tableOrder->order_number = $this->sanitizeString($sale->tableOrder->order_number);

            if ($sale->tableOrder->relationLoaded('table') && $sale->tableOrder->table) {
                $sale->tableOrder->table->name = $this->sanitizeString($sale->tableOrder->table->name);
            }
        }

        if ($sale->relationLoaded('payments')) {
            foreach ($sale->payments as $payment) {
                $payment->reference = $payment->reference === null ? null : $this->sanitizeString($payment->reference);
                $payment->status = $this->sanitizeString($payment->status);

                if ($payment->relationLoaded('paymentMethod') && $payment->paymentMethod) {
                    $payment->paymentMethod->name = $this->sanitizeString($payment->paymentMethod->name);
                    $payment->paymentMethod->code = $this->sanitizeString($payment->paymentMethod->code);
                    $payment->paymentMethod->description = $payment->paymentMethod->description === null
                        ? null
                        : $this->sanitizeString($payment->paymentMethod->description);
                }
            }
        }
    }

    private function sanitizeString(?string $value): string
    {
        $value ??= '';

        if ($value === '' || preg_match('//u', $value)) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'Windows-1252, ISO-8859-1, UTF-8');
    }
}
