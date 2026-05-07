<?php

namespace App\Services;

use App\Models\Box;
use App\Models\BoxAuditLog;
use App\Models\BoxMovement;
use App\Models\BoxSession;
use App\Models\Delivery;
use App\Models\PaymentMethod;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryCheckoutService
{
    public function __construct(
        private readonly SaleDocumentService $saleDocumentService
    ) {
    }

    public function checkout(Delivery $delivery, array $payload, int $userId): array
    {
        $paymentMethod = PaymentMethod::query()
            ->whereKey($payload['payment_method_id'])
            ->where('active', true)
            ->first();

        if (! $paymentMethod) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'Selecciona un metodo de pago activo.',
            ]);
        }

        $amountReceived = round((float) $payload['amount_received'], 2);
        $sale = null;

        DB::transaction(function () use ($delivery, $paymentMethod, $payload, $amountReceived, $userId, &$sale): void {
            $currentDelivery = Delivery::query()
                ->with(['items', 'customer', 'sale'])
                ->lockForUpdate()
                ->findOrFail($delivery->id);

            if ($currentDelivery->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'payment_method_id' => 'No se puede cobrar un domicilio cancelado.',
                ]);
            }

            if ($currentDelivery->sale) {
                throw ValidationException::withMessages([
                    'payment_method_id' => 'Este domicilio ya fue cobrado.',
                ]);
            }

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

            $totalCharge = round((float) $currentDelivery->total_charge, 2);
            $isCashPayment = $this->isCashPaymentMethod($paymentMethod);

            if ($amountReceived < $totalCharge) {
                throw ValidationException::withMessages([
                    'amount_received' => 'El monto recibido no cubre el total del domicilio.',
                ]);
            }

            if (! $isCashPayment && abs($amountReceived - $totalCharge) > 0.009) {
                throw ValidationException::withMessages([
                    'amount_received' => 'Para pagos distintos a efectivo, el monto recibido debe ser igual al total del domicilio.',
                ]);
            }

            $changeAmount = $isCashPayment
                ? round(max(0, $amountReceived - $totalCharge), 2)
                : 0.0;

            $saleNotes = collect([
                'Domicilio ' . $currentDelivery->delivery_number,
                'Direccion: ' . $currentDelivery->delivery_address,
                $currentDelivery->reference ? 'Referencia: ' . $currentDelivery->reference : null,
                $currentDelivery->notes ? 'Notas: ' . $currentDelivery->notes : null,
            ])->filter()->implode(' | ');

            $sale = Sale::query()->create([
                'user_id' => $userId,
                'box_id' => $box->id,
                'customer_id' => $currentDelivery->customer_id,
                'customer_name' => $currentDelivery->customer_name,
                'subtotal' => $totalCharge,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total' => $totalCharge,
                'status' => 'completed',
                'notes' => $saleNotes !== '' ? $saleNotes : null,
            ]);

            foreach ($currentDelivery->items as $item) {
                $sale->items()->create([
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'subtotal' => $item->subtotal,
                ]);
            }

            if ((float) $currentDelivery->delivery_fee > 0) {
                $sale->items()->create([
                    'product_id' => null,
                    'product_name' => 'Costo domicilio',
                    'quantity' => 1,
                    'unit_price' => $currentDelivery->delivery_fee,
                    'subtotal' => $currentDelivery->delivery_fee,
                ]);
            }

            $payment = $sale->payments()->create([
                'payment_method_id' => $paymentMethod->id,
                'amount' => $totalCharge,
                'received_amount' => $amountReceived,
                'change_amount' => $changeAmount,
                'tip_amount' => 0,
                'reference' => $payload['reference'] ?? null,
                'status' => 'completed',
            ]);

            $movementTotal = (float) BoxMovement::query()
                ->where('box_session_id', $boxSession->id)
                ->lockForUpdate()
                ->sum('amount');
            $balanceBefore = round((float) $box->opening_balance + $movementTotal, 2);
            $boxImpact = $this->boxImpactAmount($totalCharge, $paymentMethod);
            $balanceAfter = round($balanceBefore + $boxImpact, 2);

            $box->movements()->create([
                'box_session_id' => $boxSession->id,
                'sale_id' => $sale->id,
                'payment_id' => $payment->id,
                'user_id' => $userId,
                'movement_type' => 'delivery_payment',
                'amount' => $boxImpact,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $this->movementDescription($currentDelivery, $paymentMethod, $boxImpact),
                'occurred_at' => now(),
            ]);

            BoxAuditLog::query()->create([
                'box_id' => $box->id,
                'box_session_id' => $boxSession->id,
                'user_id' => $userId,
                'action' => 'delivery_payment',
                'description' => $this->movementDescription($currentDelivery, $paymentMethod, $boxImpact),
                'metadata' => [
                    'delivery_id' => $currentDelivery->id,
                    'sale_id' => $sale->id,
                    'payment_id' => $payment->id,
                    'amount' => $boxImpact,
                ],
                'occurred_at' => now(),
            ]);

            $currentDelivery->update([
                'sale_id' => $sale->id,
                'customer_payment_amount' => $amountReceived,
                'change_required' => $changeAmount,
            ]);
        });

        $sale->load(['items.product', 'payments.paymentMethod', 'customer', 'user', 'box', 'delivery']);

        $invoice = $this->saleDocumentService->issueTicketForSale($sale);

        return [
            'sale' => $sale->fresh(['items.product', 'payments.paymentMethod', 'customer', 'user', 'box', 'delivery', 'invoice']),
            'invoice' => $invoice->fresh(),
        ];
    }

    private function isCashPaymentMethod(PaymentMethod $paymentMethod): bool
    {
        return strtoupper((string) $paymentMethod->code) === 'CASH';
    }

    private function boxImpactAmount(float $totalCharge, PaymentMethod $paymentMethod): float
    {
        if (! $this->isCashPaymentMethod($paymentMethod)) {
            return 0.0;
        }

        return round($totalCharge, 2);
    }

    private function movementDescription(Delivery $delivery, PaymentMethod $paymentMethod, float $boxImpact): string
    {
        $parts = [
            'Cobro del domicilio ' . $delivery->delivery_number,
            'Metodo ' . $paymentMethod->name,
            $boxImpact > 0
                ? 'Impacto en caja $' . number_format($boxImpact, 2, '.', '')
                : 'Sin impacto en caja',
        ];

        return collect($parts)
            ->filter()
            ->implode(' | ');
    }
}
