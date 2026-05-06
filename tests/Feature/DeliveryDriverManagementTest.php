<?php

namespace Tests\Feature;

use App\Models\DeliveryDriver;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeliveryDriverManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_list_delivery_drivers(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $adminRole = Role::create([
            'name' => 'Admin',
            'description' => 'Administrador',
        ]);
        $user->roles()->attach($adminRole);

        $createResponse = $this
            ->actingAs($user)
            ->post(route('deliveries.drivers.store'), [
                'name' => 'Carlos Motorizado',
                'document_number' => '10203040',
                'phone' => '3009998877',
                'email' => 'carlos@example.com',
                'address' => 'Barrio Centro',
                'vehicle_type' => 'moto',
                'vehicle_plate' => 'XYZ987',
                'vehicle_model' => 'NKD 125',
                'vehicle_color' => 'Negra',
                'photo' => UploadedFile::fake()->image('driver.jpg'),
                'notes' => 'Turno noche',
                'is_active' => 1,
            ]);

        $createResponse->assertRedirect(route('deliveries.drivers.index'));

        $driver = DeliveryDriver::query()->firstOrFail();

        $this->assertDatabaseHas('delivery_drivers', [
            'id' => $driver->id,
            'name' => 'Carlos Motorizado',
            'vehicle_type' => 'moto',
            'vehicle_plate' => 'XYZ987',
            'is_active' => true,
        ]);

        $this->assertNotNull($driver->photo_path);
        Storage::disk('public')->assertExists($driver->photo_path);
        $this->actingAs($user)->get($driver->fresh()->photo_url)->assertOk();

        $updateResponse = $this
            ->actingAs($user)
            ->put(route('deliveries.drivers.update', $driver), [
                'name' => 'Carlos Motorizado',
                'document_number' => '10203040',
                'phone' => '3009998877',
                'email' => 'carlos@example.com',
                'address' => 'Barrio Norte',
                'vehicle_type' => 'bicicleta',
                'vehicle_color' => 'Roja',
                'notes' => 'Disponible fines de semana',
                'is_active' => 0,
            ]);

        $updateResponse->assertRedirect(route('deliveries.drivers.index'));

        $this->assertDatabaseHas('delivery_drivers', [
            'id' => $driver->id,
            'vehicle_type' => 'bicicleta',
            'vehicle_plate' => null,
            'vehicle_model' => null,
            'vehicle_color' => 'Roja',
            'is_active' => false,
        ]);

        $listResponse = $this
            ->actingAs($user)
            ->get(route('deliveries.drivers.index', ['search' => 'Carlos']));

        $listResponse->assertOk();
        $listResponse->assertSee('Carlos Motorizado');
        $listResponse->assertSee('Bicicleta');
    }
}
