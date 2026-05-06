<?php

namespace App\Services;

use App\Models\Box;
use App\Models\BoxAuditLog;
use App\Models\BoxMovement;
use App\Models\BoxSession;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\RestaurantTable;
use App\Models\Sale;
use App\Models\TableOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class TableOrderBillingService
{
    public function __construct(
        private readonly SaleDocumentService $saleDocumentService
    ) {
    }

    public function checkout(TableOrder $order, array $payload, int $userId): array
    {
        $documentType = $payload['document_type'] ?? Invoice::TYPE_TICKET;

        $order->loadMissing('customer');

        if ($documentType === Invoice::TYPE_ELECTRONIC) {
            $this->saleDocumentService->assertElectronicInvoiceRequirements($order->customer);
        }

        $paymentMethod = PaymentMethod::query()
            ->whereKey($payload['payment_method_id'])
            ->where('active', true)
            ->first();

        if (! $paymentMethod) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'Selecciona un método de pago activo.',
            ]);
        }

        $tipAmount = round((float) ($payload['tip_amount'] ?? 0), 2);
        $amountReceived = round((float) $payload['amount_received'], 2);
        $sale = null;
        $table = null;

        DB::transaction(function () use ($order, $paymentMethod, $payload, $tipAmount, $amountReceived, $userId, &$sale, &$table): void {
            $currentOrder = TableOrder::query()
                ->with(['items.product', 'table', 'sale', 'customer'])
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($currentOrder->status !== 'open') {
                throw ValidationException::withMessages([
                    'payment_method_id' => 'Este pedido ya fue cerrado.',
                ]);
            }

            if ($currentOrder->sale) {
                throw ValidationException::withMessages([
                    'payment_method_id' => 'Este pedido ya tiene una venta registrada.',
                ]);
            }

            $table = $currentOrder->table;
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

            $amountDue = round((float) $currentOrder->total + $tipAmount, 2);
            $isCashPayment = $this->isCashPaymentMethod($paymentMethod);

            if ($amountReceived < $amountDue) {
                throw ValidationException::withMessages([
                    'amount_received' => 'El monto recibido no cubre el total más la propina.',
                ]);
            }

            if (! $isCashPayment && abs($amountReceived - $amountDue) > 0.009) {
                throw ValidationException::withMessages([
                    'amount_received' => 'Para pagos distintos a efectivo, el monto recibido debe ser igual al total más la propina.',
                ]);
            }

            $changeAmount = $isCashPayment
                ? round(max(0, $amountReceived - $amountDue), 2)
                : 0.0;

            $saleNotes = collect([
                'Pedido ' . $currentOrder->order_number,
                $table ? 'Mesa ' . $table->name : null,
                $currentOrder->notes ? 'Notas: ' . $currentOrder->notes : null,
            ])->filter()->implode(' | ');

            $sale = Sale::query()->create([
                'user_id' => $userId,
                'box_id' => $box->id,
                'table_order_id' => $currentOrder->id,
                'customer_id' => $currentOrder->customer_id,
                'customer_name' => $currentOrder->customer_name,
                'status' => 'completed',
                'notes' => $saleNotes !== '' ? $saleNotes : null,
            ]);

            foreach ($currentOrder->items as $item) {
                $sale->items()->create([
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'subtotal' => $item->subtotal,
                ]);
            }

            $sale->calculateTotal();

            $payment = $sale->payments()->create([
                'payment_method_id' => $paymentMethod->id,
                'amount' => $sale->total,
                'received_amount' => $amountReceived,
                'change_amount' => $changeAmount,
                'tip_amount' => $tipAmount,
                'reference' => $payload['reference'] ?? null,
                'status' => 'completed',
            ]);

            $movementTotal = (float) BoxMovement::query()
                ->where('box_session_id', $boxSession->id)
                ->lockForUpdate()
                ->sum('amount');
            $balanceBefore = round((float) $box->opening_balance + $movementTotal, 2);
            $boxImpact = $this->boxImpactAmount((float) $sale->total, $tipAmount, $paymentMethod);
            $balanceAfter = round($balanceBefore + $boxImpact, 2);

            $box->movements()->create([
                'box_session_id' => $boxSession->id,
                'sale_id' => $sale->id,
                'payment_id' => $payment->id,
                'user_id' => $userId,
                'movement_type' => 'table_order_payment',
                'amount' => $boxImpact,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $this->movementDescription($currentOrder, $table, $paymentMethod, $boxImpact),
                'occurred_at' => now(),
            ]);

            BoxAuditLog::query()->create([
                'box_id' => $box->id,
                'box_session_id' => $boxSession->id,
                'user_id' => $userId,
                'action' => 'table_order_payment',
                'description' => $this->movementDescription($currentOrder, $table, $paymentMethod, $boxImpact),
                'metadata' => [
                    'sale_id' => $sale->id,
                    'payment_id' => $payment->id,
                    'amount' => $boxImpact,
                ],
                'occurred_at' => now(),
            ]);

            $currentOrder->update(['status' => 'paid']);

            if ($table) {
                $table->update(['status' => 'free']);
            }
        });

        $sale->load(['items.product', 'payments.paymentMethod', 'customer', 'tableOrder.table', 'user', 'box']);

        $invoice = null;
        $documentWarning = null;

        try {
            $invoice = $this->saleDocumentService->issueDocumentForSale($sale, $documentType);
        } catch (Throwable $exception) {
            $documentWarning = $exception->getMessage();
            $invoice = $sale->fresh('invoice')->invoice;
        }

        return [
            'sale' => $sale->fresh(['items.product', 'payments.paymentMethod', 'customer', 'tableOrder.table', 'user', 'box', 'invoice']),
            'table' => $table?->fresh(),
            'invoice' => $invoice?->fresh(),
            'document_warning' => $documentWarning,
        ];
    }

    private function isCashPaymentMethod(PaymentMethod $paymentMethod): bool
    {
        return strtoupper((string) $paymentMethod->code) === 'CASH';
    }

    private function boxImpactAmount(float $saleTotal, float $tipAmount, PaymentMethod $paymentMethod): float
    {
        if (! $this->isCashPaymentMethod($paymentMethod)) {
            return 0.0;
        }

        return round($saleTotal + $tipAmount, 2);
    }

    private function movementDescription(TableOrder $order, ?RestaurantTable $table, PaymentMethod $paymentMethod, float $boxImpact): string
    {
        $parts = [
            'Cobro del pedido ' . $order->order_number,
            $table ? 'Mesa ' . $table->name : null,
            'Método ' . $paymentMethod->name,
            $boxImpact > 0
                ? 'Impacto en caja $' . number_format($boxImpact, 2, '.', '')
                : 'Sin impacto en caja',
        ];

        return collect($parts)
            ->filter()
            ->implode(' | ');
    }
}
