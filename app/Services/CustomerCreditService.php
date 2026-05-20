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
use Illuminate\Support\Facades\DB;
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
        DB::transaction(function () use ($credit, $payload, $userId): void {
            $currentCredit = CustomerCredit::query()
                ->with('customer')
                ->lockForUpdate()
                ->findOrFail($credit->id);

            if ($currentCredit->status !== 'pending') {
                throw ValidationException::withMessages([
                    'amount_received' => 'Este saldo ya fue pagado.',
                ]);
            }

            $paymentMethod = $this->resolvePaymentMethod($payload['payment_method_id'] ?? null);
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
            $runningBalance = round((float) $box->opening_balance + $movementTotal, 2);

            $this->applyPaymentToCredit($currentCredit, $amountReceived, $payload, $userId, $paymentMethod, $box, $boxSession, $runningBalance);
        });
    }

    public function payCustomerBalance(Customer $customer, array $payload, int $userId): array
    {
        return DB::transaction(function () use ($customer, $payload, $userId): array {
            $pendingCredits = CustomerCredit::query()
                ->with('customer')
                ->where('customer_id', $customer->id)
                ->where('status', 'pending')
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($pendingCredits->isEmpty()) {
                throw ValidationException::withMessages([
                    'amount_received' => 'Este cliente no tiene deuda pendiente.',
                ]);
            }

            $paymentMethod = $this->resolvePaymentMethod($payload['payment_method_id'] ?? null);
            [$box, $boxSession] = $this->resolveOpenBoxForUser($userId);

            $amountReceived = round((float) $payload['amount_received'], 2);
            $totalPending = round((float) $pendingCredits->sum('balance'), 2);

            if ($amountReceived <= 0 || $amountReceived > $totalPending) {
                throw ValidationException::withMessages([
                    'amount_received' => 'El abono debe ser mayor a cero y no puede superar la deuda total pendiente.',
                ]);
            }

            $movementTotal = (float) BoxMovement::query()
                ->where('box_session_id', $boxSession->id)
                ->lockForUpdate()
                ->sum('amount');
            $runningBalance = round((float) $box->opening_balance + $movementTotal, 2);
            $remainingToApply = $amountReceived;
            $appliedCredits = 0;

            foreach ($pendingCredits as $pendingCredit) {
                if ($remainingToApply <= 0) {
                    break;
                }

                $creditBalance = round((float) $pendingCredit->balance, 2);
                $appliedAmount = round(min($creditBalance, $remainingToApply), 2);

                if ($appliedAmount <= 0) {
                    continue;
                }

                $this->applyPaymentToCredit($pendingCredit, $appliedAmount, $payload, $userId, $paymentMethod, $box, $boxSession, $runningBalance);

                $remainingToApply = round($remainingToApply - $appliedAmount, 2);
                $appliedCredits++;
            }

            return [
                'amount_applied' => $amountReceived,
                'total_pending' => $totalPending,
                'remaining_pending' => round(max(0, $totalPending - $amountReceived), 2),
                'applied_credits' => $appliedCredits,
            ];
        });
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

    private function resolvePaymentMethod(mixed $paymentMethodId): ?PaymentMethod
    {
        if (empty($paymentMethodId)) {
            return null;
        }

        $paymentMethod = PaymentMethod::query()
            ->whereKey($paymentMethodId)
            ->where('active', true)
            ->first();

        if (! $paymentMethod) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'Selecciona un metodo de pago activo.',
            ]);
        }

        return $paymentMethod;
    }

    private function applyPaymentToCredit(
        CustomerCredit $credit,
        float $amountReceived,
        array $payload,
        int $userId,
        ?PaymentMethod $paymentMethod,
        Box $box,
        BoxSession $boxSession,
        float &$runningBalance
    ): void {
        $credit->loadMissing('customer');

        $creditBalance = round((float) $credit->balance, 2);
        $remainingBalance = round(max(0, $creditBalance - $amountReceived), 2);
        $balanceBefore = $runningBalance;
        $balanceAfter = round($balanceBefore + $amountReceived, 2);
        $description = ($remainingBalance <= 0 ? 'Pago final de cartera #' : 'Abono de cartera #') . $credit->id
            . ' | Cliente ' . $credit->customer->name;

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
                'customer_credit_id' => $credit->id,
                'customer_id' => $credit->customer_id,
                'sale_id' => $credit->sale_id,
                'amount' => $amountReceived,
                'remaining_balance' => $remainingBalance,
            ],
            'occurred_at' => now(),
        ]);

        $credit->update([
            'balance' => $remainingBalance,
            'status' => $remainingBalance <= 0 ? 'paid' : 'pending',
            'payment_method_id' => $paymentMethod?->id,
            'paid_reference' => $payload['reference'] ?? null,
            'paid_at' => $remainingBalance <= 0 ? now() : null,
        ]);

        $runningBalance = $balanceAfter;

        if ($credit->sale_id) {
            $this->syncSaleCreditPayment($credit, $amountReceived, $remainingBalance, $paymentMethod, $payload['reference'] ?? null);
        }
    }

    private function syncSaleCreditPayment(
        CustomerCredit $credit,
        float $amountReceived,
        float $remainingBalance,
        ?PaymentMethod $paymentMethod,
        ?string $reference
    ): void {
        $sale = Sale::query()
            ->with('payments')
            ->lockForUpdate()
            ->find($credit->sale_id);

        if (! $sale) {
            return;
        }

        $payment = $sale->payments->first();
        $paymentStatus = $remainingBalance <= 0 ? 'completed' : 'pending';

        if ($payment) {
            $payment->update([
                'payment_method_id' => $paymentMethod?->id,
                'received_amount' => round((float) ($payment->received_amount ?? 0) + $amountReceived, 2),
                'change_amount' => 0,
                'reference' => $reference ?? $payment->reference,
                'status' => $paymentStatus,
            ]);
        } else {
            $sale->payments()->create([
                'payment_method_id' => $paymentMethod?->id,
                'amount' => $sale->total,
                'received_amount' => $amountReceived,
                'change_amount' => 0,
                'tip_amount' => 0,
                'reference' => $reference,
                'status' => $paymentStatus,
            ]);
        }

        $sale->update([
            'status' => $remainingBalance <= 0 ? 'completed' : 'credit',
            'payment_status' => $remainingBalance <= 0 ? 'paid' : 'credit',
            'credit_due_at' => $remainingBalance <= 0 ? null : $sale->credit_due_at,
        ]);
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
