<?php

namespace App\Services;

use App\Models\Box;
use App\Models\BoxAuditLog;
use App\Models\BoxMovement;
use App\Models\BoxSession;
use App\Models\Customer;
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
        private readonly SaleDocumentService $saleDocumentService,
        private readonly CustomerCreditService $customerCreditService,
        private readonly CustomerBalanceService $customerBalanceService
    ) {
    }

    public function checkout(TableOrder $order, array $payload, int $userId): array
    {
        $documentType = $payload['document_type'] ?? Invoice::TYPE_TICKET;
        $isCredit = (bool) ($payload['is_credit'] ?? false);
        $applyCustomerBalance = (bool) ($payload['apply_customer_balance'] ?? false);
        $customerSelectionProvided = array_key_exists('customer_id', $payload);
        $selectedCustomerId = $customerSelectionProvided && ! blank($payload['customer_id'] ?? null)
            ? (int) $payload['customer_id']
            : null;

        $order->loadMissing('customer');

        $selectedCustomer = null;

        if ($selectedCustomerId) {
            $selectedCustomer = Customer::query()
                ->whereKey($selectedCustomerId)
                ->where('is_active', true)
                ->first();

            if (! $selectedCustomer) {
                throw ValidationException::withMessages([
                    'customer_id' => 'Selecciona un cliente activo para continuar con el cobro.',
                ]);
            }
        }

        $billingCustomer = $customerSelectionProvided
            ? $selectedCustomer
            : $order->customer;

        if ($documentType === Invoice::TYPE_ELECTRONIC) {
            $this->saleDocumentService->assertElectronicInvoiceRequirements($billingCustomer);
        }

        $paymentMethod = null;

        if (! $isCredit && ! empty($payload['payment_method_id'])) {
            $paymentMethod = PaymentMethod::query()
                ->whereKey($payload['payment_method_id'] ?? null)
                ->where('active', true)
                ->first();

            if (! $paymentMethod) {
                throw ValidationException::withMessages([
                    'payment_method_id' => 'Selecciona un metodo de pago activo.',
                ]);
            }
        }

        $tipAmount = $isCredit
            ? 0.0
            : round((float) ($payload['tip_amount'] ?? 0), 2);
        $amountReceived = round((float) $payload['amount_received'], 2);
        $sale = null;
        $table = null;

        DB::transaction(function () use ($order, $paymentMethod, $payload, $tipAmount, $amountReceived, $userId, $isCredit, $applyCustomerBalance, $customerSelectionProvided, &$sale, &$table): void {
            $currentOrder = TableOrder::query()
                ->with(['items.product.taxRate', 'table', 'sale', 'customer'])
                ->lockForUpdate()
                ->findOrFail($order->id);

            $currentOrder->recalculateTotals();

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

            $selectedCustomer = null;

            if ($customerSelectionProvided && ! blank($payload['customer_id'] ?? null)) {
                $selectedCustomer = Customer::query()
                    ->whereKey((int) $payload['customer_id'])
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if (! $selectedCustomer) {
                    throw ValidationException::withMessages([
                        'customer_id' => 'Selecciona un cliente activo para continuar con el cobro.',
                    ]);
                }
            }

            if ($customerSelectionProvided) {
                $currentOrder->customer_id = $selectedCustomer?->id;
                $currentOrder->customer_name = $selectedCustomer?->name;
            }

            if ($isCredit && ! $currentOrder->customer_id) {
                throw ValidationException::withMessages([
                    'customer_id' => 'Selecciona un cliente antes de enviar la cuenta a credito.',
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
                'status' => $isCredit ? 'credit' : 'completed',
                'payment_status' => $isCredit ? 'credit' : 'paid',
                'credit_due_at' => null,
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

            $appliedCustomerBalance = 0.0;
            $remainingSaleAmount = round((float) $sale->total, 2);

            if (! $isCredit && $applyCustomerBalance && $currentOrder->customer_id) {
                $balanceApplication = $this->customerBalanceService->applyAvailableBalance(
                    $selectedCustomer ?: $currentOrder->customer,
                    $sale,
                    (float) $sale->total,
                    $userId,
                    'Consumo descontado desde saldo a favor en pedido ' . $currentOrder->order_number
                );

                $appliedCustomerBalance = $balanceApplication['applied_amount'];
                $remainingSaleAmount = $balanceApplication['remaining_amount'];
            }

            $amountDue = round($remainingSaleAmount + $tipAmount, 2);
            $isCashPayment = $this->isCashPaymentMethod($paymentMethod);

            if (! $isCredit && $amountDue > 0 && ! $paymentMethod) {
                throw ValidationException::withMessages([
                    'payment_method_id' => 'Selecciona un metodo de pago activo.',
                ]);
            }

            if (! $isCredit && $amountDue > 0 && $amountReceived < $amountDue) {
                throw ValidationException::withMessages([
                    'amount_received' => 'El monto recibido no cubre el total a cobrar.',
                ]);
            }

            if (! $isCredit && $amountDue > 0 && ! $isCashPayment && abs($amountReceived - $amountDue) > 0.009) {
                throw ValidationException::withMessages([
                    'amount_received' => 'Para pagos distintos a efectivo, el monto recibido debe ser igual al total a cobrar.',
                ]);
            }

            $changeAmount = $isCredit
                ? 0.0
                : ($isCashPayment
                    ? round(max(0, $amountReceived - $amountDue), 2)
                    : 0.0);

            $payment = $sale->payments()->create([
                'payment_method_id' => $isCredit || $amountDue <= 0 ? null : $paymentMethod?->id,
                'amount' => $isCredit ? $sale->total : $remainingSaleAmount,
                'received_amount' => $isCredit ? 0 : $amountReceived,
                'change_amount' => $changeAmount,
                'tip_amount' => $isCredit ? 0 : $tipAmount,
                'reference' => $amountDue <= 0 && $appliedCustomerBalance > 0
                    ? 'Saldo a favor aplicado automaticamente'
                    : ($payload['reference'] ?? null),
                'status' => $isCredit ? 'pending' : 'completed',
            ]);

            $movementTotal = (float) BoxMovement::query()
                ->where('box_session_id', $boxSession->id)
                ->lockForUpdate()
                ->sum('amount');
            $balanceBefore = round((float) $box->opening_balance + $movementTotal, 2);
            $boxImpact = $isCredit
                ? 0.0
                : $this->boxImpactAmount($remainingSaleAmount, $tipAmount, $paymentMethod);
            $balanceAfter = round($balanceBefore + $boxImpact, 2);
            $description = $this->movementDescription($currentOrder, $table, $paymentMethod, $boxImpact, $isCredit, $appliedCustomerBalance);

            $box->movements()->create([
                'box_session_id' => $boxSession->id,
                'sale_id' => $sale->id,
                'payment_id' => $payment->id,
                'user_id' => $userId,
                'movement_type' => 'table_order_payment',
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
                'action' => 'table_order_payment',
                'description' => $description,
                'metadata' => [
                    'sale_id' => $sale->id,
                    'payment_id' => $payment->id,
                    'amount' => $boxImpact,
                    'is_credit' => $isCredit,
                    'applied_customer_balance' => $appliedCustomerBalance,
                ],
                'occurred_at' => now(),
            ]);

            if ($isCredit) {
                $this->customerCreditService->registerCreditForSale(
                    $sale,
                    'table_order',
                    $currentOrder->order_number,
                    'Cuenta enviada a credito desde el pedido ' . $currentOrder->order_number
                );
            }

            $currentOrder->update([
                'status' => 'paid',
                'customer_id' => $currentOrder->customer_id,
                'customer_name' => $currentOrder->customer_name,
            ]);

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

    private function movementDescription(
        TableOrder $order,
        ?RestaurantTable $table,
        ?PaymentMethod $paymentMethod,
        float $boxImpact,
        bool $isCredit = false,
        float $appliedCustomerBalance = 0
    ): string {
        $parts = [
            'Cobro del pedido ' . $order->order_number,
            $table ? 'Mesa ' . $table->name : null,
            $isCredit ? 'Credito al cliente' : 'Metodo ' . ($paymentMethod?->name ?? 'Sin dato'),
            $appliedCustomerBalance > 0
                ? 'Saldo a favor aplicado $' . number_format($appliedCustomerBalance, 2, '.', '')
                : null,
            $boxImpact > 0
                ? 'Impacto en caja $' . number_format($boxImpact, 2, '.', '')
                : 'Sin impacto en caja',
        ];

        return collect($parts)
            ->filter()
            ->implode(' | ');
    }
}
