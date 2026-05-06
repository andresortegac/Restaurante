<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryDriver;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeliveryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_complete_and_list_deliveries(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
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

        $driver = DeliveryDriver::create([
            'name' => 'Repartidor Uno',
            'phone' => '3002223344',
            'vehicle_type' => 'moto',
            'vehicle_plate' => 'ABC123',
            'is_active' => true,
        ]);

        $createResponse = $this
            ->actingAs($user)
            ->post(route('deliveries.store'), [
                'delivery_number' => 'DOM-20260423-0001',
                'customer_id' => $customer->id,
                'delivery_driver_id' => $driver->id,
                'customer_name' => 'Cliente Domicilio',
                'customer_phone' => '3001112233',
                'delivery_address' => 'Calle 10 # 20-30',
                'reference' => 'Casa azul',
                'order_total' => 35000,
                'delivery_fee' => 5000,
                'customer_payment_amount' => 50000,
                'status' => 'assigned',
                'scheduled_at' => now()->addHour()->format('Y-m-d H:i:s'),
                'notes' => 'Entregar rapido',
            ]);

        $createResponse->assertRedirect(route('deliveries.index'));

        $delivery = Delivery::query()->firstOrFail();

        $this->assertDatabaseHas('deliveries', [
            'id' => $delivery->id,
            'delivery_number' => 'DOM-20260423-0001',
            'status' => 'assigned',
            'total_charge' => '40000.00',
            'change_required' => '10000.00',
        ]);

        $completeResponse = $this
            ->actingAs($user)
            ->put(route('deliveries.complete', $delivery), [
                'delivery_proof_image' => UploadedFile::fake()->image('delivery-proof.jpg'),
            ]);

        $completeResponse->assertRedirect(route('deliveries.index'));

        $delivery->refresh();

        $this->assertSame('delivered', $delivery->status);
        $this->assertNotNull($delivery->delivered_at);
        $this->assertNotNull($delivery->delivery_proof_image_path);
        Storage::disk('public')->assertExists($delivery->delivery_proof_image_path);
        $this->actingAs($user)->get($delivery->delivery_proof_image_url)->assertOk();

        $listResponse = $this
            ->actingAs($user)
            ->get(route('deliveries.index', ['search' => 'DOM-20260423-0001']));

        $listResponse->assertOk();
        $listResponse->assertSee('DOM-20260423-0001');
        $listResponse->assertSee('Cliente Domicilio');
        $listResponse->assertSee('Repartidor Uno');
    }

    public function test_customer_payment_amount_must_cover_total_charge(): void
    {
        $user = User::factory()->create();
        $adminRole = Role::create([
            'name' => 'Admin',
            'description' => 'Administrador',
        ]);
        $user->roles()->attach($adminRole);

        $response = $this
            ->actingAs($user)
            ->from(route('deliveries.create'))
            ->post(route('deliveries.store'), [
                'delivery_number' => 'DOM-20260423-0002',
                'customer_name' => 'Cliente Cambio',
                'customer_phone' => '3005556677',
                'delivery_address' => 'Carrera 5 # 10-15',
                'order_total' => 40000,
                'delivery_fee' => 5000,
                'customer_payment_amount' => 30000,
                'status' => 'pending',
            ]);

        $response->assertRedirect(route('deliveries.create'));
        $response->assertSessionHasErrors('customer_payment_amount');
    }
}
