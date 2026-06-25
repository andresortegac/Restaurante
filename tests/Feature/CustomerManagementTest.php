<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Customer;
use App\Models\CustomerCredit;
use App\Models\CustomerPaymentReceipt;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_list_customers(): void
    {
        $user = User::factory()->create();
        $adminRole = Role::create([
            'name' => 'Admin',
            'description' => 'Administrador',
        ]);
        $user->roles()->attach($adminRole);

        $createResponse = $this
            ->actingAs($user)
            ->post(route('customers.store'), [
                'name' => 'Laura Gomez',
                'document_number' => 'CC-1001',
                'phone' => '3001234567',
                'email' => 'laura@example.com',
                'billing_address' => 'Calle 10 # 20-30',
                'identification_document_code' => '13',
                'legal_organization_code' => '2',
                'tribute_code' => 'ZZ',
                'municipality_code' => '68001',
                'notes' => 'Cliente frecuente',
                'is_active' => 1,
            ]);

        $createResponse->assertRedirect(route('customers.index'));

        $customer = Customer::query()->firstOrFail();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Laura Gomez',
            'document_number' => 'CC-1001',
            'billing_identification' => 'CC-1001',
            'billing_address' => 'Calle 10 # 20-30',
            'is_active' => true,
        ]);

        $updateResponse = $this
            ->actingAs($user)
            ->put(route('customers.update', $customer), [
                'name' => 'Laura Gomez Restrepo',
                'document_number' => 'CC-1001',
                'phone' => '3007654321',
                'email' => 'laura.restrepo@example.com',
                'billing_address' => 'Carrera 5 # 6-70',
                'identification_document_code' => '13',
                'legal_organization_code' => '2',
                'tribute_code' => 'ZZ',
                'municipality_code' => '68001',
                'notes' => 'Actualizada',
                'is_active' => 1,
            ]);

        $updateResponse->assertRedirect(route('customers.index'));

        $listResponse = $this
            ->actingAs($user)
            ->get(route('customers.index', ['search' => 'Laura']));

        $listResponse->assertOk();
        $listResponse->assertSee('Laura Gomez Restrepo');
        $listResponse->assertSee('CC-1001');
        $listResponse->assertSee('Saldo a favor');
        $listResponse->assertDontSee('Saldo pendiente');
    }

    public function test_admin_can_add_remove_and_review_customer_available_balance(): void
    {
        $user = User::factory()->create();
        $adminRole = Role::create([
            'name' => 'Admin',
            'description' => 'Administrador',
        ]);
        $user->roles()->attach($adminRole);

        $customer = Customer::create([
            'name' => 'Cliente Anticipo',
            'document_number' => 'CC-7777',
            'is_active' => true,
        ]);

        $addResponse = $this
            ->actingAs($user)
            ->post(route('customers.credits.balance.store', $customer), [
                'operation' => 'add',
                'description' => 'Anticipo de caja externa',
                'amount' => 100000,
            ]);

        $addResponse->assertRedirect(route('customers.credits.show', $customer));

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'available_balance' => 100000,
        ]);

        $this->assertDatabaseHas('customer_balance_movements', [
            'customer_id' => $customer->id,
            'movement_type' => 'manual_addition',
            'description' => 'Anticipo de caja externa',
            'amount' => 100000,
            'balance_before' => 0,
            'balance_after' => 100000,
        ]);

        $removeResponse = $this
            ->actingAs($user)
            ->post(route('customers.credits.balance.store', $customer), [
                'operation' => 'remove',
                'description' => 'Ajuste solicitado por cliente',
                'amount' => 25000,
            ]);

        $removeResponse->assertRedirect(route('customers.credits.show', $customer));

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'available_balance' => 75000,
        ]);

        $this->assertDatabaseHas('customer_balance_movements', [
            'customer_id' => $customer->id,
            'movement_type' => 'manual_removal',
            'description' => 'Ajuste solicitado por cliente',
            'amount' => -25000,
            'balance_before' => 100000,
            'balance_after' => 75000,
        ]);

        $indexResponse = $this
            ->actingAs($user)
            ->get(route('customers.index'));

        $indexResponse->assertOk();
        $indexResponse->assertSee('Cliente Anticipo');
        $indexResponse->assertSee('$75,000');
        $indexResponse->assertSee('Saldo');

        $creditsIndexResponse = $this
            ->actingAs($user)
            ->get(route('customers.credits.index'));

        $creditsIndexResponse->assertRedirect(route('customers.index'));

        $showResponse = $this
            ->actingAs($user)
            ->get(route('customers.credits.show', $customer));

        $showResponse->assertOk();
        $showResponse->assertSee('Saldo a favor');
        $showResponse->assertSee('Facturas');
        $showResponse->assertSee('Tirilla deuda');
        $showResponse->assertSee('Historial de pagos');
        $showResponse->assertDontSee('Historial saldo a favor');
        $showResponse->assertSee('Consumido');
        $showResponse->assertSee('Le queda');
        $showResponse->assertSee('Tope');
        $showResponse->assertDontSee('Asignar saldo pendiente');
        $showResponse->assertDontSee('Ver historial del credito');
        $showResponse->assertDontSee('Cobrar deuda del cliente');

        $box = Box::create([
            'name' => 'Caja saldo cliente',
            'code' => 'BOX-BALANCE-HISTORY',
            'user_id' => $user->id,
            'opening_balance' => 0,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $sale = Sale::create([
            'user_id' => $user->id,
            'box_id' => $box->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'subtotal' => 30000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total' => 30000,
            'status' => 'completed',
            'payment_status' => 'paid',
            'notes' => 'Pedido de mesa manual',
        ]);

        Invoice::create([
            'sale_id' => $sale->id,
            'invoice_number' => 'TICKET-TEST-001',
            'invoice_type' => Invoice::TYPE_TICKET,
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $customer->balanceMovements()->create([
            'sale_id' => $sale->id,
            'created_by_user_id' => $user->id,
            'movement_type' => 'sale_consumption',
            'description' => 'Consumo descontado desde saldo a favor en cobro manual #' . $sale->id,
            'amount' => -30000,
            'balance_before' => 75000,
            'balance_after' => 45000,
        ]);

        $historyResponse = $this
            ->actingAs($user)
            ->get(route('customers.credits.balance-history', $customer));

        $historyResponse->assertOk();
        $historyResponse->assertSee('Historial del saldo a favor');
        $historyResponse->assertSee('Anticipo de caja externa');
        $historyResponse->assertSee('Ajuste solicitado por cliente');
        $historyResponse->assertSee('Venta #' . $sale->id);
        $historyResponse->assertSee('TICKET-TEST-001');
        $historyResponse->assertSee(route('pos.sales.print', $sale));

        $debtSummaryResponse = $this
            ->actingAs($user)
            ->get(route('customers.credits.debt-summary.print', $customer));

        $debtSummaryResponse->assertOk();
        $debtSummaryResponse->assertSee('Total pendiente');
        $debtSummaryResponse->assertSee('Total a cobrar');
        $debtSummaryResponse->assertDontSee('Saldo a favor');
        $debtSummaryResponse->assertDontSee('Creditos pendientes');

        $filteredHistoryResponse = $this
            ->actingAs($user)
            ->get(route('customers.credits.balance-history', [
                $customer,
                'ticket' => 'TICKET-TEST-001',
            ]));

        $filteredHistoryResponse->assertOk();
        $filteredHistoryResponse->assertSee('TICKET-TEST-001');
        $filteredHistoryResponse->assertSee(route('pos.sales.print', $sale));
        $filteredHistoryResponse->assertDontSee('Anticipo de caja externa');
        $filteredHistoryResponse->assertDontSee('Ajuste solicitado por cliente');

        $printableHistoryResponse = $this
            ->actingAs($user)
            ->get(route('customers.credits.balance-history', [
                $customer,
                'printable' => 1,
            ]));

        $printableHistoryResponse->assertOk();
        $printableHistoryResponse->assertSee('Solo con recibo para imprimir');
        $printableHistoryResponse->assertSee('TICKET-TEST-001');
        $printableHistoryResponse->assertDontSee('Anticipo de caja externa');
        $printableHistoryResponse->assertDontSee('Ajuste solicitado por cliente');

        $consumedInvoicesResponse = $this
            ->actingAs($user)
            ->get(route('customers.credits.consumed-invoices', [
                $customer,
                'invoice' => 'TICKET-TEST-001',
            ]));

        $consumedInvoicesResponse->assertOk();
        $consumedInvoicesResponse->assertSee('Factura o ticket');
        $consumedInvoicesResponse->assertSee('TICKET-TEST-001');
        $consumedInvoicesResponse->assertSee(route('pos.sales.print', $sale));
    }

    public function test_admin_can_collect_customer_debt_and_print_payment_receipt(): void
    {
        $user = User::factory()->create();
        $adminRole = Role::create([
            'name' => 'Admin',
            'description' => 'Administrador',
        ]);
        $user->roles()->attach($adminRole);

        $customer = Customer::create([
            'name' => 'Cliente Cartera',
            'document_number' => 'CC-9001',
            'phone' => '3000000000',
            'email' => 'cartera@example.com',
            'billing_address' => 'Calle deuda 123',
            'is_active' => true,
        ]);

        CustomerCredit::create([
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'source_type' => 'manual_assignment',
            'description' => 'Consumo pendiente',
            'amount' => 80000,
            'balance' => 80000,
            'status' => 'pending',
        ]);

        $box = Box::create([
            'name' => 'Caja cartera',
            'code' => 'BOX-CUSTOMER-DEBT',
            'user_id' => $user->id,
            'opening_balance' => 10000,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSee('Cobrar')
            ->assertSee('Debe $80,000');

        $this->actingAs($user)
            ->get(route('customers.credits.collect', $customer))
            ->assertOk()
            ->assertSee('Cobro de deuda')
            ->assertSee('Historial de pago')
            ->assertSee('Nota')
            ->assertDontSee('Deuda pendiente')
            ->assertDontSee('Ultimos recibos')
            ->assertDontSee('Consumo pendiente');

        $response = $this
            ->actingAs($user)
            ->post(route('customers.credits.collect.store', $customer), [
                'payment_mode' => 'partial',
                'amount_received' => 30000,
                'reference' => 'ABONO-001',
            ]);

        $receipt = CustomerPaymentReceipt::query()->firstOrFail();

        $response->assertRedirect(route('customers.credits.receipts.print', [$customer, $receipt]));

        $this->assertDatabaseHas('customer_credits', [
            'customer_id' => $customer->id,
            'balance' => '50000.00',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('box_movements', [
            'box_id' => $box->id,
            'user_id' => $user->id,
            'movement_type' => 'customer_credit_payment',
            'amount' => '30000.00',
            'balance_before' => '10000.00',
            'balance_after' => '40000.00',
        ]);

        $this->assertDatabaseHas('customer_payment_receipts', [
            'id' => $receipt->id,
            'customer_id' => $customer->id,
            'amount' => '30000.00',
            'box_impact' => '30000.00',
            'remaining_pending' => '50000.00',
            'reference' => 'ABONO-001',
        ]);

        $this->actingAs($user)
            ->get(route('customers.credits.payments.history', $customer))
            ->assertOk()
            ->assertSee('Factura o recibo')
            ->assertSee(route('customers.credits.show', $customer))
            ->assertSee($receipt->receipt_number)
            ->assertSee('ABONO-001')
            ->assertSee(route('customers.credits.receipts.print', [$customer, $receipt]));

        $this->actingAs($user)
            ->get(route('customers.credits.payments.history', [
                $customer,
                'invoice' => 'ABONO-001',
            ]))
            ->assertOk()
            ->assertSee($receipt->receipt_number)
            ->assertSee('ABONO-001');

        $this->actingAs($user)
            ->get(route('customers.credits.receipts.print', [$customer, $receipt]))
            ->assertOk()
            ->assertSee('SOLOMO &amp; POMO', false)
            ->assertDontSee('Laravel')
            ->assertSee('Recibo de pago')
            ->assertSee('Abono')
            ->assertSee('Nota')
            ->assertSee('ABONO-001')
            ->assertSee($receipt->receipt_number)
            ->assertSee('Cliente Cartera')
            ->assertSee('$30,000')
            ->assertDontSee('Caja')
            ->assertDontSee('Recibido por')
            ->assertDontSee('Impacto caja')
            ->assertDontSee('Saldo anterior')
            ->assertDontSee('Saldo despues');
    }

    public function test_admin_can_collect_consumed_customer_balance_as_debt(): void
    {
        $user = User::factory()->create();
        $adminRole = Role::create([
            'name' => 'Admin',
            'description' => 'Administrador',
        ]);
        $user->roles()->attach($adminRole);

        $customer = Customer::create([
            'name' => 'Cliente Cupo',
            'document_number' => 'CC-9090',
            'phone' => '3001112233',
            'email' => 'cupo@example.com',
            'billing_address' => 'Calle cupo 123',
            'available_balance' => 0,
            'is_active' => true,
        ]);

        $box = Box::create([
            'name' => 'Caja cupo cliente',
            'code' => 'BOX-CUSTOMER-BALANCE-DEBT',
            'user_id' => $user->id,
            'opening_balance' => 10000,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $sale = Sale::create([
            'user_id' => $user->id,
            'box_id' => $box->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'subtotal' => 100000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total' => 100000,
            'status' => 'completed',
            'payment_status' => 'paid',
        ]);

        $customer->balanceMovements()->create([
            'sale_id' => $sale->id,
            'created_by_user_id' => $user->id,
            'movement_type' => 'sale_consumption',
            'description' => 'Consumo descontado desde saldo a favor',
            'amount' => -100000,
            'balance_before' => 100000,
            'balance_after' => 0,
        ]);

        $this->actingAs($user)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSee('Cliente Cupo')
            ->assertSee('Debe $100,000')
            ->assertSee('Cobrar deuda');

        $this->actingAs($user)
            ->get(route('customers.credits.collect', $customer))
            ->assertOk()
            ->assertSee('Cobro de deuda')
            ->assertSee('Nota')
            ->assertSee('$100,000')
            ->assertDontSee('Saldo a favor consumido')
            ->assertDontSee('Ultimos recibos');

        $response = $this
            ->actingAs($user)
            ->post(route('customers.credits.collect.store', $customer), [
                'payment_mode' => 'partial',
                'amount_received' => 40000,
                'reference' => 'PAGO-CUPO-001',
            ]);

        $receipt = CustomerPaymentReceipt::query()->firstOrFail();

        $response->assertRedirect(route('customers.credits.receipts.print', [$customer, $receipt]));

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'available_balance' => 40000,
        ]);

        $this->assertDatabaseHas('customer_balance_movements', [
            'customer_id' => $customer->id,
            'movement_type' => 'customer_payment',
            'amount' => 40000,
            'balance_before' => 0,
            'balance_after' => 40000,
        ]);

        $this->assertDatabaseHas('box_movements', [
            'box_id' => $box->id,
            'user_id' => $user->id,
            'movement_type' => 'customer_balance_payment',
            'amount' => '40000.00',
            'balance_before' => '10000.00',
            'balance_after' => '50000.00',
        ]);

        $this->assertDatabaseHas('customer_payment_receipts', [
            'id' => $receipt->id,
            'customer_id' => $customer->id,
            'amount' => '40000.00',
            'remaining_pending' => '60000.00',
            'reference' => 'PAGO-CUPO-001',
        ]);

        $this->actingAs($user)
            ->get(route('customers.credits.debt-summary.print', $customer))
            ->assertOk()
            ->assertSee('Consumo pendiente')
            ->assertSee('$60,000')
            ->assertDontSee('Saldo a favor')
            ->assertDontSee('El cliente no tiene deuda pendiente.');

        $this->actingAs($user)
            ->get(route('customers.credits.receipts.print', [$customer, $receipt]))
            ->assertOk()
            ->assertSee('SOLOMO &amp; POMO', false)
            ->assertSee('Abono')
            ->assertSee('Nota')
            ->assertDontSee('Caja')
            ->assertDontSee('Recibido por')
            ->assertDontSee('Impacto caja')
            ->assertDontSee('Saldo a favor consumido')
            ->assertDontSee('Saldo anterior')
            ->assertDontSee('Saldo despues');

        $fullPaymentResponse = $this
            ->actingAs($user)
            ->post(route('customers.credits.collect.store', $customer), [
                'payment_mode' => 'full',
                'reference' => 'PAGO-CUPO-FINAL',
            ]);

        $finalReceipt = CustomerPaymentReceipt::query()->latest('id')->firstOrFail();

        $fullPaymentResponse->assertRedirect(route('customers.credits.receipts.print', [$customer, $finalReceipt]));

        $this->actingAs($user)
            ->get(route('customers.credits.receipts.print', [$customer, $finalReceipt]))
            ->assertOk()
            ->assertSee('Pago completo')
            ->assertSee('PAGO-CUPO-FINAL')
            ->assertSee('$60,000')
            ->assertSee('Saldo pendiente')
            ->assertSee('$0');
    }
}
