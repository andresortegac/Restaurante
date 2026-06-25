<?php

namespace App\Services;

use App\Models\Box;
use App\Models\BoxAuditLog;
use App\Models\BoxMovement;
use App\Models\BoxSession;
use App\Models\Customer;
use App\Models\CustomerBalanceMovement;
use App\Models\CustomerCredit;
use App\Models\CustomerPaymentReceipt;
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
        mixed $dueAt = null,
        ?float $creditAmount = null
    ): ?CustomerCredit {
        if (! $sale->customer_id) {
            return null;
        }

        $amount = money_value($creditAmount ?? $sale->total);

        if ($amount <= 0) {
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
                'amount' => $amount,
                'balance' => $amount,
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
            'description' => $payload['description'] ?? 'Saldo pendiente manual del cliente ' . $customer->name,
            'amount' => money_value($payload['amount']),
            'balance' => money_value($payload['amount']),
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

            $amountReceived = money_value($payload['amount_received']);
            $creditBalance = money_value($currentCredit->balance);

            if ($amountReceived <= 0 || $amountReceived > $creditBalance) {
                throw ValidationException::withMessages([
                    'amount_received' => 'El abono debe ser mayor a cero y no puede superar el saldo pendiente.',
                ]);
            }

            $movementTotal = (float) BoxMovement::query()
                ->where('box_session_id', $boxSession->id)
                ->lockForUpdate()
                ->sum('amount');
            $runningBalance = money_value((float) $box->opening_balance + $movementTotal);

            $allocation = $this->applyPaymentToCredit($currentCredit, $amountReceived, $payload, $userId, $paymentMethod, $box, $boxSession, $runningBalance);

            $this->createPaymentReceipt(
                $currentCredit->customer,
                $box,
                $boxSession,
                $paymentMethod,
                $userId,
                $amountReceived,
                $this->boxImpactAmount($amountReceived, $paymentMethod),
                $allocation['remaining_balance'],
                $payload['reference'] ?? null,
                [$allocation]
            );
        });
    }

    public function payCustomerBalance(Customer $customer, array $payload, int $userId): array
    {
        return DB::transaction(function () use ($customer, $payload, $userId): array {
            $currentCustomer = Customer::query()
                ->lockForUpdate()
                ->findOrFail($customer->id);

            $balanceDebt = $this->customerBalanceDebt($currentCustomer);
            $pendingCredits = CustomerCredit::query()
                ->with('customer')
                ->where('customer_id', $customer->id)
                ->where('status', 'pending')
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($pendingCredits->isEmpty() && $balanceDebt <= 0) {
                throw ValidationException::withMessages([
                    'amount_received' => 'Este cliente no tiene deuda pendiente.',
                ]);
            }

            $paymentMethod = $this->resolvePaymentMethod($payload['payment_method_id'] ?? null);
            [$box, $boxSession] = $this->resolveOpenBoxForUser($userId);

            $amountReceived = money_value($payload['amount_received']);
            $totalPending = money_value((float) $pendingCredits->sum('balance') + $balanceDebt);

            if ($amountReceived <= 0 || $amountReceived > $totalPending) {
                throw ValidationException::withMessages([
                    'amount_received' => 'El abono debe ser mayor a cero y no puede superar la deuda total pendiente.',
                ]);
            }

            $movementTotal = (float) BoxMovement::query()
                ->where('box_session_id', $boxSession->id)
                ->lockForUpdate()
                ->sum('amount');
            $runningBalance = money_value((float) $box->opening_balance + $movementTotal);
            $remainingToApply = $amountReceived;
            $appliedCredits = 0;
            $allocations = [];

            foreach ($pendingCredits as $pendingCredit) {
                if ($remainingToApply <= 0) {
                    break;
                }

                $creditBalance = money_value($pendingCredit->balance);
                $appliedAmount = money_value(min($creditBalance, $remainingToApply));

                if ($appliedAmount <= 0) {
                    continue;
                }

                $allocations[] = $this->applyPaymentToCredit($pendingCredit, $appliedAmount, $payload, $userId, $paymentMethod, $box, $boxSession, $runningBalance);

                $remainingToApply = money_value($remainingToApply - $appliedAmount);
                $appliedCredits++;
            }

            if ($remainingToApply > 0 && $balanceDebt > 0) {
                $appliedAmount = money_value(min($balanceDebt, $remainingToApply));

                if ($appliedAmount > 0) {
                    $allocations[] = $this->applyPaymentToCustomerBalanceDebt(
                        $currentCustomer,
                        $appliedAmount,
                        $balanceDebt,
                        $payload,
                        $userId,
                        $paymentMethod,
                        $box,
                        $boxSession,
                        $runningBalance
                    );

                    $remainingToApply = money_value($remainingToApply - $appliedAmount);
                }
            }

            $remainingPending = money_value(max(0, $totalPending - $amountReceived));
            $receipt = $this->createPaymentReceipt(
                $currentCustomer,
                $box,
                $boxSession,
                $paymentMethod,
                $userId,
                $amountReceived,
                $this->boxImpactAmount($amountReceived, $paymentMethod),
                $remainingPending,
                $payload['reference'] ?? null,
                $allocations
            );

            return [
                'amount_applied' => $amountReceived,
                'total_pending' => $totalPending,
                'remaining_pending' => $remainingPending,
                'applied_credits' => $appliedCredits,
                'receipt' => $receipt,
            ];
        });
    }

    private function applyPaymentToCustomerBalanceDebt(
        Customer $customer,
        float $amountReceived,
        float $currentBalanceDebt,
        array $payload,
        int $userId,
        ?PaymentMethod $paymentMethod,
        Box $box,
        BoxSession $boxSession,
        float &$runningBalance
    ): array {
        $boxBalanceBefore = $runningBalance;
        $boxImpact = $this->boxImpactAmount($amountReceived, $paymentMethod);
        $boxBalanceAfter = money_value($boxBalanceBefore + $boxImpact);
        $remainingBalanceDebt = money_value(max(0, $currentBalanceDebt - $amountReceived));
        $description = ($remainingBalanceDebt <= 0 ? 'Pago final de saldo a favor consumido' : 'Abono de saldo a favor consumido')
            . ' | Cliente ' . $customer->name
            . ' | Metodo ' . ($paymentMethod?->name ?? 'Efectivo')
            . ' | ' . ($boxImpact > 0 ? 'Impacto en caja $' . money($boxImpact) : 'Sin impacto en caja');

        $movement = $box->movements()->create([
            'box_session_id' => $boxSession->id,
            'user_id' => $userId,
            'movement_type' => 'customer_balance_payment',
            'amount' => $boxImpact,
            'balance_before' => $boxBalanceBefore,
            'balance_after' => $boxBalanceAfter,
            'description' => $description,
            'occurred_at' => now(),
        ]);

        BoxAuditLog::query()->create([
            'box_id' => $box->id,
            'box_session_id' => $boxSession->id,
            'user_id' => $userId,
            'action' => 'customer_balance_payment',
            'description' => $description,
            'metadata' => [
                'movement_id' => $movement->id,
                'customer_id' => $customer->id,
                'amount' => $amountReceived,
                'box_impact' => $boxImpact,
                'payment_method_id' => $paymentMethod?->id,
                'remaining_balance_debt' => $remainingBalanceDebt,
            ],
            'occurred_at' => now(),
        ]);

        $customerBalanceBefore = money_value($customer->available_balance);
        $customerBalanceAfter = money_value($customerBalanceBefore + $amountReceived);

        $customer->update([
            'available_balance' => $customerBalanceAfter,
        ]);

        $customer->balanceMovements()->create([
            'sale_id' => null,
            'created_by_user_id' => $userId,
            'movement_type' => 'customer_payment',
            'description' => $payload['reference'] ?? 'Pago de saldo a favor consumido',
            'amount' => $amountReceived,
            'balance_before' => $customerBalanceBefore,
            'balance_after' => $customerBalanceAfter,
        ]);

        $runningBalance = $boxBalanceAfter;

        return [
            'type' => 'customer_balance',
            'description' => 'Saldo a favor consumido',
            'amount' => $amountReceived,
            'balance_before' => $currentBalanceDebt,
            'remaining_balance' => $remainingBalanceDebt,
            'box_movement_id' => $movement->id,
        ];
    }

    private function customerBalanceDebt(Customer $customer): float
    {
        $movements = CustomerBalanceMovement::query()
            ->where('customer_id', $customer->id)
            ->whereIn('movement_type', ['sale_consumption', 'customer_payment'])
            ->lockForUpdate()
            ->get(['movement_type', 'amount']);

        $consumed = abs(money_value((float) $movements
            ->where('movement_type', 'sale_consumption')
            ->sum('amount')));
        $paid = money_value((float) $movements
            ->where('movement_type', 'customer_payment')
            ->sum('amount'));

        return money_value(max(0, $consumed - $paid));
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
            ->systemAllowed()
            ->whereKey($paymentMethodId)
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
    ): array {
        $credit->loadMissing('customer');

        $creditBalance = money_value($credit->balance);
        $remainingBalance = money_value(max(0, $creditBalance - $amountReceived));
        $balanceBefore = $runningBalance;
        $boxImpact = $this->boxImpactAmount($amountReceived, $paymentMethod);
        $balanceAfter = money_value($balanceBefore + $boxImpact);
        $description = ($remainingBalance <= 0 ? 'Pago final de cartera #' : 'Abono de cartera #') . $credit->id
            . ' | Cliente ' . $credit->customer->name
            . ' | Metodo ' . ($paymentMethod?->name ?? 'Efectivo')
            . ' | ' . ($boxImpact > 0 ? 'Impacto en caja $' . money($boxImpact) : 'Sin impacto en caja');

        $movement = $box->movements()->create([
            'box_session_id' => $boxSession->id,
            'user_id' => $userId,
            'movement_type' => 'customer_credit_payment',
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
            'action' => 'customer_credit_payment',
            'description' => $description,
            'metadata' => [
                'movement_id' => $movement->id,
                'customer_credit_id' => $credit->id,
                'customer_id' => $credit->customer_id,
                'sale_id' => $credit->sale_id,
                'amount' => $amountReceived,
                'box_impact' => $boxImpact,
                'payment_method_id' => $paymentMethod?->id,
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

        return [
            'credit_id' => $credit->id,
            'sale_id' => $credit->sale_id,
            'description' => $credit->description,
            'amount' => $amountReceived,
            'balance_before' => $creditBalance,
            'remaining_balance' => $remainingBalance,
            'box_movement_id' => $movement->id,
        ];
    }

    private function createPaymentReceipt(
        Customer $customer,
        Box $box,
        BoxSession $boxSession,
        ?PaymentMethod $paymentMethod,
        int $userId,
        float $amountReceived,
        float $boxImpact,
        float $remainingPending,
        ?string $reference,
        array $allocations
    ): CustomerPaymentReceipt {
        return CustomerPaymentReceipt::query()->create([
            'receipt_number' => $this->nextReceiptNumber(),
            'customer_id' => $customer->id,
            'box_id' => $box->id,
            'box_session_id' => $boxSession->id,
            'box_movement_id' => $allocations[0]['box_movement_id'] ?? null,
            'payment_method_id' => $paymentMethod?->id,
            'received_by_user_id' => $userId,
            'amount' => money_value($amountReceived),
            'box_impact' => money_value($boxImpact),
            'remaining_pending' => money_value($remainingPending),
            'reference' => $reference,
            'allocations' => $allocations,
            'paid_at' => now(),
        ]);
    }

    private function nextReceiptNumber(): string
    {
        do {
            $receiptNumber = 'REC-' . now()->format('YmdHis') . '-' . random_int(100, 999);
        } while (CustomerPaymentReceipt::query()->where('receipt_number', $receiptNumber)->exists());

        return $receiptNumber;
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
                'received_amount' => money_value((float) ($payment->received_amount ?? 0) + $amountReceived),
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

    private function boxImpactAmount(float $amountReceived, ?PaymentMethod $paymentMethod): float
    {
        if (! $this->isCashPaymentMethod($paymentMethod)) {
            return 0.0;
        }

        return money_value($amountReceived);
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
