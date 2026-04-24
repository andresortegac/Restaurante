<?php

namespace App\Services\Factus;

use App\Models\Customer;
use App\Models\ElectronicInvoiceSetting;
use App\Models\Payment;
use App\Models\Sale;

class FactusInvoicePayloadBuilder
{
    public function build(Sale $sale, ElectronicInvoiceSetting $settings, string $referenceCode): array
    {
        $sale->loadMissing(['items.product.taxRate', 'customer', 'payments.paymentMethod']);

        $customer = $sale->customer;

        if (!$customer) {
            throw new FactusApiException('La venta requiere un cliente asociado para emitir factura electrónica.');
        }

        $this->ensureCustomerData($customer, $settings);

        return [
            'reference_code' => $referenceCode,
            'document' => $settings->document_code,
            'numbering_range_id' => $settings->numbering_range_id,
            'operation_type' => $settings->operation_type,
            'send_email' => $settings->send_email,
            'observation' => $sale->notes,
            'customer' => $this->customerPayload($customer, $settings),
            'payment_details' => $this->paymentDetails($sale),
            'items' => $this->itemDetails($sale, $settings),
        ];
    }

    private function ensureCustomerData(Customer $customer, ElectronicInvoiceSetting $settings): void
    {
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

        $missing = collect($required)
            ->filter(fn ($value) => blank($value))
            ->keys()
            ->values()
            ->all();

        if ($missing !== []) {
            throw new FactusApiException('El cliente no tiene todos los datos requeridos para Factus.', ['missing_fields' => $missing]);
        }
    }

    private function customerPayload(Customer $customer, ElectronicInvoiceSetting $settings): array
    {
        return [
            'identification_document_code' => $customer->identification_document_code ?: $settings->default_identification_document_code,
            'identification' => $customer->billing_identification ?: $customer->document_number,
            'company' => $customer->name,
            'trade_name' => $customer->trade_name ?: $customer->name,
            'names' => $customer->name,
            'address' => $customer->billing_address,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'legal_organization_code' => $customer->legal_organization_code ?: $settings->default_legal_organization_code,
            'tribute_code' => $customer->tribute_code ?: $settings->default_tribute_code,
            'municipality_code' => $customer->municipality_code ?: $settings->default_municipality_code,
        ];
    }

    private function paymentDetails(Sale $sale): array
    {
        if ($sale->payments->isEmpty()) {
            throw new FactusApiException('La venta no tiene pagos registrados para construir la factura electrónica.');
        }

        return $sale->payments
            ->map(function (Payment $payment): array {
                $code = strtoupper((string) ($payment->paymentMethod->code ?? ''));
                $paymentForm = $code === 'CASH' ? '1' : '2';
                $paymentMethodCode = match ($code) {
                    'CASH' => '10',
                    'CARD' => '48',
                    'TRANSFER' => '42',
                    default => '10',
                };

                $row = [
                    'payment_form' => $paymentForm,
                    'payment_method_code' => $paymentMethodCode,
                    'reference_code' => $payment->reference ?: 'sale-payment-' . $payment->id,
                    'amount' => number_format((float) $payment->amount, 2, '.', ''),
                ];

                if ($paymentForm === '2') {
                    $row['due_date'] = optional($sale->created_at)->toDateString() ?? now()->toDateString();
                }

                return $row;
            })
            ->values()
            ->all();
    }

    private function itemDetails(Sale $sale, ElectronicInvoiceSetting $settings): array
    {
        return $sale->items
            ->map(function ($item) use ($settings): array {
                $taxRate = (float) ($item->product?->taxRate?->rate ?? 19);

                return [
                    'code_reference' => $item->product?->sku ?: 'ITEM-' . $item->id,
                    'name' => $item->product_name ?: $item->product?->name ?: 'Producto',
                    'quantity' => number_format((float) $item->quantity, 2, '.', ''),
                    'discount_rate' => '0.00',
                    'price' => number_format((float) $item->unit_price, 2, '.', ''),
                    'unit_measure_code' => $settings->default_unit_measure_code,
                    'standard_code' => $settings->default_standard_code,
                    'taxes' => [[
                        'code' => '01',
                        'rate' => number_format($taxRate, 2, '.', ''),
                    ]],
                ];
            })
            ->values()
            ->all();
    }
}
