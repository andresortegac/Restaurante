<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_list_deliveries(): void
    {
        $user = User::factory()->create();
        $assignedUser = User::factory()->create(['name' => 'Repartidor Uno']);
        $adminRole = Role::create([
            'name' => 'Admin',
            'description' => 'Administrador',
        ]);
        $user->roles()->attach($adminRole);

        $customer = Customer::create([
            'name' => 'Cliente Domicilio',
            'phone' => '3001112233',
            'is_active' => true,
        ]);

        $createResponse = $this
            ->actingAs($user)
            ->post(route('deliveries.store'), [
                'delivery_number' => 'DOM-20260423-0001',
                'customer_id' => $customer->id,
                'assigned_user_id' => $assignedUser->id,
                'customer_name' => 'Cliente Domicilio',
                'customer_phone' => '3001112233',
                'delivery_address' => 'Calle 10 # 20-30',
                'reference' => 'Casa azul',
                'order_total' => 35000,
                'delivery_fee' => 5000,
                'status' => 'assigned',
                'scheduled_at' => now()->addHour()->format('Y-m-d H:i:s'),
                'notes' => 'Entregar rápido',
            ]);

        $createResponse->assertRedirect(route('deliveries.index'));

        $delivery = Delivery::query()->firstOrFail();

        $this->assertDatabaseHas('deliveries', [
            'id' => $delivery->id,
            'delivery_number' => 'DOM-20260423-0001',
            'status' => 'assigned',
            'total_charge' => '40000.00',
        ]);

        $updateResponse = $this
            ->actingAs($user)
            ->put(route('deliveries.update', $delivery), [
                'delivery_number' => 'DOM-20260423-0001',
                'customer_id' => $customer->id,
                'assigned_user_id' => $assignedUser->id,
                'customer_name' => 'Cliente Domicilio',
                'customer_phone' => '3001112233',
                'delivery_address' => 'Calle 10 # 20-30',
                'reference' => 'Casa azul',
                'order_total' => 35000,
                'delivery_fee' => 5000,
                'status' => 'delivered',
                'scheduled_at' => now()->addHour()->format('Y-m-d H:i:s'),
                'notes' => 'Entregado',
            ]);

        $updateResponse->assertRedirect(route('deliveries.index'));

        $listResponse = $this
            ->actingAs($user)
            ->get(route('deliveries.index', ['search' => 'DOM-20260423-0001']));

        $listResponse->assertOk();
        $listResponse->assertSee('DOM-20260423-0001');
        $listResponse->assertSee('Cliente Domicilio');
    }
}
