<?php

namespace App\Services;

use App\Models\Box;
use App\Models\BoxAuditLog;
use App\Models\BoxMovement;
use App\Models\BoxSession;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ManualBillingService
{
    public function __construct(
        private readonly SaleDocumentService $saleDocumentService
    ) {
    }

    public function checkout(array $payload, int $userId): array
    {
        $documentType = $payload['document_type'] ?? Invoice::TYPE_TICKET;
        $originType = $payload['origin_type'] ?? 'table';
        $isCredit = (bool) ($payload['is_credit'] ?? false);

        $customer = null;

        if (! empty($payload['customer_id'])) {
            $customer = Customer::query()->find($payload['customer_id']);
        }

        if ($documentType === Invoice::TYPE_ELECTRONIC) {
            $this->saleDocumentService->assertElectronicInvoiceRequirements($customer);
        }

        if ($isCredit && ! $customer && ! filled($payload['customer_name'] ?? null)) {
            throw ValidationException::withMessages([
                'customer_id' => 'Selecciona o escribe el cliente para registrar un credito.',
            ]);
        }

        $paymentMethod = null;

        if (! empty($payload['payment_method_id'])) {
            $paymentMethod = PaymentMethod::query()
                ->whereKey($payload['payment_method_id'])
                ->where('active', true)
                ->first();

            if (! $paymentMethod) {
                throw ValidationException::withMessages([
                    'payment_method_id' => 'Selecciona un metodo de pago activo.',
                ]);
            }
        }

        $tipAmount = round((float) ($payload['tip_amount'] ?? 0), 2);
        $amountReceived = round((float) $payload['amount_received'], 2);
        $items = collect($payload['items'] ?? [])
            ->filter(fn (array $item) => filled($item['name'] ?? null) && (float) ($item['quantity'] ?? 0) > 0)
            ->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Agrega al menos un concepto para registrar el cobro manual.',
            ]);
        }

        $sale = null;

        DB::transaction(function () use ($payload, $paymentMethod, $items, $tipAmount, $amountReceived, $userId, $originType, $customer, $isCredit, &$sale): void {
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
                throw ValidationException::withMessages([
                    'payment_method_id' => 'No hay una caja abierta para registrar este cobro.',
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

            $sale = Sale::query()->create([
                'user_id' => $userId,
                'box_id' => $box->id,
                'customer_id' => $customer?->id,
                'customer_name' => $payload['customer_name'] ?? $customer?->name,
                'status' => $isCredit ? 'credit' : 'completed',
                'payment_status' => $isCredit ? 'credit' : 'paid',
                'credit_due_at' => $isCredit ? ($payload['credit_due_at'] ?? null) : null,
                'notes' => $this->saleNotes($payload, $originType),
            ]);

            foreach ($items as $item) {
                $product = null;

                if (! empty($item['product_id'])) {
                    $product = Product::query()
                        ->sellable()
                        ->whereKey($item['product_id'])
                        ->lockForUpdate()
                        ->first();
                }

                $quantity = (int) $item['quantity'];
                $unitPrice = round((float) $item['unit_price'], 2);
                $subtotal = round($quantity * $unitPrice, 2);

                if ($subtotal <= 0) {
                    throw ValidationException::withMessages([
                        'items' => 'Cada concepto debe tener cantidad y valor mayores a cero.',
                    ]);
                }

                if ($product && $product->tracks_stock) {
                    if (! $product->isInStock($quantity)) {
                        throw ValidationException::withMessages([
                            'items' => 'No hay stock suficiente para ' . $product->name . '.',
                        ]);
                    }

                    $product->reduceStock($quantity);
                }

                $sale->items()->create([
                    'product_id' => $product?->id,
                    'product_name' => $product?->name ?: $item['name'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ]);
            }

            $sale->calculateTotal();

            $amountDue = round((float) $sale->total + $tipAmount, 2);
            $isCashPayment = $this->isCashPaymentMethod($paymentMethod);

            if (! $isCredit && $amountReceived < $amountDue) {
                throw ValidationException::withMessages([
                    'amount_received' => 'El monto recibido no cubre el total mas la propina.',
                ]);
            }

            if (! $isCredit && ! $isCashPayment && abs($amountReceived - $amountDue) > 0.009) {
                throw ValidationException::withMessages([
                    'amount_received' => 'Para pagos distintos a efectivo, el monto recibido debe ser igual al total mas la propina.',
                ]);
            }

            $changeAmount = $isCredit
                ? 0.0
                : ($isCashPayment
                ? round(max(0, $amountReceived - $amountDue), 2)
                : 0.0);

            $payment = $sale->payments()->create([
                'payment_method_id' => $paymentMethod?->id,
                'amount' => $sale->total,
                'received_amount' => $isCredit ? 0 : $amountReceived,
                'change_amount' => $changeAmount,
                'tip_amount' => $isCredit ? 0 : $tipAmount,
                'reference' => $payload['reference'] ?? null,
                'status' => $isCredit ? 'pending' : 'completed',
            ]);

            $movementTotal = (float) BoxMovement::query()
                ->where('box_session_id', $boxSession->id)
                ->lockForUpdate()
                ->sum('amount');
            $balanceBefore = round((float) $box->opening_balance + $movementTotal, 2);
            $boxImpact = $isCredit
                ? 0.0
                : $this->boxImpactAmount((float) $sale->total, $tipAmount, $paymentMethod);
            $balanceAfter = round($balanceBefore + $boxImpact, 2);
            $description = $this->movementDescription($sale, $originType, $paymentMethod, $boxImpact, $isCredit);

            $box->movements()->create([
                'box_session_id' => $boxSession->id,
                'sale_id' => $sale->id,
                'payment_id' => $payment->id,
                'user_id' => $userId,
                'movement_type' => 'manual_payment',
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
                'action' => 'manual_payment',
                'description' => $description,
                'metadata' => [
                    'sale_id' => $sale->id,
                    'origin_type' => $originType,
                    'payment_id' => $payment->id,
                    'amount' => $boxImpact,
                    'is_credit' => $isCredit,
                ],
                'occurred_at' => now(),
            ]);
        });

        $sale->load(['items.product', 'payments.paymentMethod', 'customer', 'user', 'box']);

        $invoice = null;
        $documentWarning = null;

        try {
            $invoice = $this->saleDocumentService->issueDocumentForSale($sale, $documentType);
        } catch (Throwable $exception) {
            $documentWarning = $exception->getMessage();
            $invoice = $sale->fresh('invoice')->invoice;
        }

        return [
            'sale' => $sale->fresh(['items.product', 'payments.paymentMethod', 'customer', 'user', 'box', 'invoice']),
            'invoice' => $invoice?->fresh(),
            'document_warning' => $documentWarning,
        ];
    }

    private function saleNotes(array $payload, string $originType): string
    {
        $originLabel = $originType === 'delivery' ? 'Domicilio manual' : 'Pedido de mesa manual';

        return collect([
            $originLabel,
            filled($payload['origin_reference'] ?? null) ? 'Referencia: ' . $payload['origin_reference'] : null,
            filled($payload['delivery_address'] ?? null) ? 'Direccion: ' . $payload['delivery_address'] : null,
            filled($payload['notes'] ?? null) ? 'Notas: ' . $payload['notes'] : null,
        ])->filter()->implode(' | ');
    }

    private function isCashPaymentMethod(?PaymentMethod $paymentMethod): bool
    {
        if (! $paymentMethod) {
            return true;
        }

        return strtoupper((string) $paymentMethod->code) === 'CASH';
    }

    private function boxImpactAmount(float $saleTotal, float $tipAmount, ?PaymentMethod $paymentMethod): float
    {
        if (! $this->isCashPaymentMethod($paymentMethod)) {
            return 0.0;
        }

        return round($saleTotal + $tipAmount, 2);
    }

    private function movementDescription(Sale $sale, string $originType, ?PaymentMethod $paymentMethod, float $boxImpact, bool $isCredit = false): string
    {
        $parts = [
            'Cobro manual #' . $sale->id,
            $originType === 'delivery' ? 'Domicilio' : 'Mesa',
            $isCredit ? 'Credito' : 'Metodo ' . ($paymentMethod?->name ?? 'Sin dato'),
            $boxImpact > 0
                ? 'Impacto en caja $' . number_format($boxImpact, 2, '.', '')
                : 'Sin impacto en caja',
        ];

        return collect($parts)
            ->filter()
            ->implode(' | ');
    }
}
