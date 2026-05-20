<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Customer;
use App\Models\Role;
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
                'notes' => 'Cliente frecuente',
                'is_active' => 1,
            ]);

        $createResponse->assertRedirect(route('customers.index'));

        $customer = Customer::query()->firstOrFail();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Laura Gomez',
            'document_number' => 'CC-1001',
            'is_active' => true,
        ]);

        $updateResponse = $this
            ->actingAs($user)
            ->put(route('customers.update', $customer), [
                'name' => 'Laura Gomez Restrepo',
                'document_number' => 'CC-1001',
                'phone' => '3007654321',
                'email' => 'laura.restrepo@example.com',
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
    }

    public function test_admin_can_assign_and_collect_manual_customer_credit(): void
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
            'is_active' => true,
        ]);

        $assignResponse = $this
            ->actingAs($user)
            ->post(route('customers.credits.store', $customer), [
                'description' => 'Saldo inicial',
                'amount' => 45000,
            ]);

        $assignResponse->assertRedirect(route('customers.credits.show', $customer));

        $this->assertDatabaseHas('customer_credits', [
            'customer_id' => $customer->id,
            'description' => 'Saldo inicial',
            'amount' => 45000,
            'balance' => 45000,
            'status' => 'pending',
        ]);

        $creditIndexResponse = $this
            ->actingAs($user)
            ->get(route('customers.credits.index'));

        $creditIndexResponse->assertOk();
        $creditIndexResponse->assertSee('Gestion de creditos');
        $creditIndexResponse->assertSee('Cliente Cartera');
        $creditIndexResponse->assertSee('$45,000.00');

        Box::create([
            'name' => 'Caja cartera clientes',
            'code' => 'BOX-CUSTOMER-CREDIT',
            'user_id' => $user->id,
            'opening_balance' => 30000,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $creditId = \App\Models\CustomerCredit::query()->value('id');

        $payResponse = $this
            ->actingAs($user)
            ->post(route('customers.credits.pay', [$customer, $creditId]), [
                'amount_received' => 20000,
            ]);

        $payResponse->assertRedirect(route('customers.credits.show', $customer));

        $this->assertDatabaseHas('customer_credits', [
            'id' => $creditId,
            'balance' => 25000,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('box_movements', [
            'movement_type' => 'customer_credit_payment',
            'amount' => 20000,
            'balance_before' => 30000,
            'balance_after' => 50000,
        ]);

        $finalPayResponse = $this
            ->actingAs($user)
            ->post(route('customers.credits.pay', [$customer, $creditId]), [
                'amount_received' => 25000,
            ]);

        $finalPayResponse->assertRedirect(route('customers.credits.show', $customer));

        $this->assertDatabaseHas('customer_credits', [
            'id' => $creditId,
            'balance' => 0,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('box_movements', [
            'movement_type' => 'customer_credit_payment',
            'amount' => 25000,
            'balance_before' => 50000,
            'balance_after' => 75000,
        ]);
    }
}
