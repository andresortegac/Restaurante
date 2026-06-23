<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Customer;
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
        $showResponse->assertSee('Ver historial del saldo a favor');
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
    }
}
