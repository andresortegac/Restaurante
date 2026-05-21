<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerBalanceMovement;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerBalanceService
{
    public function registerManualMovement(Customer $customer, array $payload, int $userId): CustomerBalanceMovement
    {
        return DB::transaction(function () use ($customer, $payload, $userId): CustomerBalanceMovement {
            $currentCustomer = Customer::query()
                ->lockForUpdate()
                ->findOrFail($customer->id);

            $operation = (string) ($payload['operation'] ?? 'add');
            $amount = money_value($payload['amount'] ?? 0);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'El valor debe ser mayor a cero.',
                ]);
            }

            $balanceBefore = money_value($currentCustomer->available_balance);
            $signedAmount = $operation === 'remove'
                ? -$amount
                : $amount;

            if ($operation === 'remove' && $amount > $balanceBefore) {
                throw ValidationException::withMessages([
                    'amount' => 'No puedes descontar un valor mayor al saldo a favor disponible.',
                ]);
            }

            $balanceAfter = money_value($balanceBefore + $signedAmount);

            $currentCustomer->update([
                'available_balance' => $balanceAfter,
            ]);

            return $currentCustomer->balanceMovements()->create([
                'sale_id' => null,
                'created_by_user_id' => $userId,
                'movement_type' => $operation === 'remove' ? 'manual_removal' : 'manual_addition',
                'description' => $payload['description'] ?? $this->defaultManualDescription($currentCustomer, $operation),
                'amount' => $signedAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
            ]);
        });
    }

    public function applyAvailableBalance(Customer $customer, Sale $sale, float $saleAmount, int $userId, ?string $description = null): array
    {
        $currentCustomer = Customer::query()
            ->lockForUpdate()
            ->findOrFail($customer->id);

        $balanceBefore = money_value($currentCustomer->available_balance);
        $amountToApply = money_value(min($balanceBefore, max(0, $saleAmount)));

        if ($amountToApply <= 0) {
            return [
                'applied_amount' => 0.0,
                'remaining_amount' => money_value(max(0, $saleAmount)),
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceBefore,
            ];
        }

        $balanceAfter = money_value($balanceBefore - $amountToApply);

        $currentCustomer->update([
            'available_balance' => $balanceAfter,
        ]);

        $currentCustomer->balanceMovements()->create([
            'sale_id' => $sale->id,
            'created_by_user_id' => $userId,
            'movement_type' => 'sale_consumption',
            'description' => $description ?: 'Consumo aplicado a la venta #' . $sale->id,
            'amount' => -$amountToApply,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
        ]);

        return [
            'applied_amount' => $amountToApply,
            'remaining_amount' => money_value(max(0, $saleAmount - $amountToApply)),
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
        ];
    }

    private function defaultManualDescription(Customer $customer, string $operation): string
    {
        return $operation === 'remove'
            ? 'Descuento manual de saldo a favor del cliente ' . $customer->name
            : 'Ingreso manual de saldo a favor del cliente ' . $customer->name;
    }
}
