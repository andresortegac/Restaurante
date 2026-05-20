<?php

namespace App\Services;

use App\Models\Box;
use App\Models\BoxAuditLog;
use App\Models\BoxMovement;
use App\Models\BoxSession;
use App\Models\Customer;
use App\Models\CustomerCredit;
use App\Models\PaymentMethod;
use App\Models\Sale;
use Illuminate\Validation\ValidationException;

class CustomerCreditService
{
    public function registerCreditForSale(
        Sale $sale,
        string $sourceType,
        ?string $sourceReference = null,
        ?string $description = null,
        mixed $dueAt = null
    ): ?CustomerCredit {
        if (! $sale->customer_id) {
            return null;
        }

        return CustomerCredit::query()->updateOrCreate(
            ['sale_id' => $sale->id],
            [
                'customer_id' => $sale->customer_id,
                'created_by_user_id' => $sale->user_id,
                'payment_method_id' => null,
                'source_type' => $sourceType,
                'source_reference' => $sourceReference,
                'description' => $description ?: $this->defaultSaleDescription($sale, $sourceType),
                'amount' => round((float) $sale->total, 2),
                'balance' => round((float) $sale->total, 2),
                'status' => 'pending',
                'due_at' => $dueAt ?: $sale->credit_due_at,
                'paid_at' => null,
                'paid_reference' => null,
            ]
        );
    }

    public function markSaleCreditAsPaid(Sale $sale, ?PaymentMethod $paymentMethod = null, ?string $reference = null): void
    {
        $credit = CustomerCredit::query()
            ->where('sale_id', $sale->id)
            ->first();

        if (! $credit) {
            return;
        }

        $credit->update([
            'balance' => 0,
            'status' => 'paid',
            'payment_method_id' => $paymentMethod?->id,
            'paid_reference' => $reference,
            'paid_at' => now(),
        ]);
    }

    public function assignPendingBalance(Customer $customer, array $payload, int $userId): CustomerCredit
    {
        return CustomerCredit::query()->create([
            'customer_id' => $customer->id,
            'created_by_user_id' => $userId,
            'source_type' => 'manual_assignment',
            'source_reference' => $payload['source_reference'] ?? null,
            'description' => $payload['description'],
            'amount' => round((float) $payload['amount'], 2),
            'balance' => round((float) $payload['amount'], 2),
            'status' => 'pending',
            'due_at' => $payload['due_at'] ?? null,
        ]);
    }

    public function payAssignedCredit(CustomerCredit $credit, array $payload, int $userId): void
    {
        $currentCredit = CustomerCredit::query()
            ->with('customer')
            ->lockForUpdate()
            ->findOrFail($credit->id);

        if ($currentCredit->status !== 'pending') {
            throw ValidationException::withMessages([
                'amount_received' => 'Este saldo ya fue pagado.',
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

        [$box, $boxSession] = $this->resolveOpenBoxForUser($userId);

        $amountReceived = round((float) $payload['amount_received'], 2);
        $creditBalance = round((float) $currentCredit->balance, 2);

        if ($amountReceived <= 0 || $amountReceived > $creditBalance) {
            throw ValidationException::withMessages([
                'amount_received' => 'El abono debe ser mayor a cero y no puede superar el saldo pendiente.',
            ]);
        }

        $movementTotal = (float) BoxMovement::query()
            ->where('box_session_id', $boxSession->id)
            ->lockForUpdate()
            ->sum('amount');
        $balanceBefore = round((float) $box->opening_balance + $movementTotal, 2);
        $balanceAfter = round($balanceBefore + $amountReceived, 2);
        $remainingBalance = round(max(0, $creditBalance - $amountReceived), 2);
        $description = 'Abono de cartera #' . $currentCredit->id . ' | Cliente ' . $currentCredit->customer->name;

        $box->movements()->create([
            'box_session_id' => $boxSession->id,
            'user_id' => $userId,
            'movement_type' => 'customer_credit_payment',
            'amount' => $amountReceived,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => $description,
            'occurred_at' => now(),
        ]);

        BoxAuditLog::query()->create([
            'box_id' => $box->id,
            'box_session_id' => $boxSession->id,
            'user_id' => $userId,
            'action' => 'customer_credit_payment',
            'description' => $description,
            'metadata' => [
                'customer_credit_id' => $currentCredit->id,
                'customer_id' => $currentCredit->customer_id,
                'amount' => $amountReceived,
                'remaining_balance' => $remainingBalance,
            ],
            'occurred_at' => now(),
        ]);

        $currentCredit->update([
            'balance' => $remainingBalance,
            'status' => $remainingBalance <= 0 ? 'paid' : 'pending',
            'payment_method_id' => $paymentMethod?->id,
            'paid_reference' => $payload['reference'] ?? null,
            'paid_at' => $remainingBalance <= 0 ? now() : null,
        ]);
    }

    private function resolveOpenBoxForUser(int $userId): array
    {
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
                'amount_received' => 'No hay una caja abierta para registrar el pago del saldo.',
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

        return [$box, $boxSession];
    }

    private function isCashPaymentMethod(?PaymentMethod $paymentMethod): bool
    {
        if (! $paymentMethod) {
            return true;
        }

        return strtoupper((string) $paymentMethod->code) === 'CASH';
    }

    private function defaultSaleDescription(Sale $sale, string $sourceType): string
    {
        return match ($sourceType) {
            'table_order' => 'Cuenta enviada a credito desde el pedido #' . ($sale->tableOrder?->order_number ?: $sale->id),
            'manual_charge' => 'Saldo enviado a credito desde cobro manual #' . $sale->id,
            default => 'Saldo generado por la venta #' . $sale->id,
        };
    }
}
