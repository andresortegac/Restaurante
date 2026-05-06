<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_only_lists_free_tables(): void
    {
        $user = $this->adminUser();

        $freeTable = RestaurantTable::query()->create([
            'name' => 'Mesa Libre',
            'code' => 'ML-01',
            'area' => 'Principal',
            'capacity' => 4,
            'status' => 'free',
            'is_active' => true,
        ]);

        RestaurantTable::query()->create([
            'name' => 'Mesa Ocupada',
            'code' => 'MO-01',
            'area' => 'Principal',
            'capacity' => 4,
            'status' => 'occupied',
            'is_active' => true,
        ]);

        RestaurantTable::query()->create([
            'name' => 'Mesa Reservada',
            'code' => 'MR-01',
            'area' => 'Principal',
            'capacity' => 4,
            'status' => 'reserved',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('reservations.create'));

        $response->assertOk();
        $response->assertSee($freeTable->name);
        $response->assertDontSee('Mesa Ocupada');
        $response->assertDontSee('Mesa Reservada');
    }

    public function test_store_rejects_non_free_tables_and_accepts_deposit_amount(): void
    {
        $user = $this->adminUser();

        $occupiedTable = RestaurantTable::query()->create([
            'name' => 'Mesa Ocupada',
            'code' => 'MO-02',
            'area' => 'Principal',
            'capacity' => 4,
            'status' => 'occupied',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('reservations.create'))
            ->post(route('reservations.store'), [
                'restaurant_table_id' => $occupiedTable->id,
                'customer_name' => 'Laura Gomez',
                'customer_phone' => '3001234567',
                'customer_email' => 'laura@example.com',
                'reservation_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'party_size' => 4,
                'status' => 'pending',
                'notes' => 'Celebracion familiar',
                'deposit_amount' => '50000',
            ]);

        $response->assertRedirect(route('reservations.create'));
        $response->assertSessionHasErrors('restaurant_table_id');
        $this->assertDatabaseCount('reservations', 0);

        $freeTable = RestaurantTable::query()->create([
            'name' => 'Mesa Libre',
            'code' => 'ML-02',
            'area' => 'Principal',
            'capacity' => 4,
            'status' => 'free',
            'is_active' => true,
        ]);

        $createResponse = $this
            ->actingAs($user)
            ->post(route('reservations.store'), [
                'restaurant_table_id' => $freeTable->id,
                'customer_name' => 'Laura Gomez',
                'customer_phone' => '3001234567',
                'customer_email' => 'laura@example.com',
                'reservation_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'party_size' => 4,
                'status' => 'pending',
                'notes' => 'Celebracion familiar',
                'deposit_amount' => '50000',
            ]);

        $createResponse->assertRedirect(route('reservations.index'));

        $this->assertDatabaseHas('reservations', [
            'restaurant_table_id' => $freeTable->id,
            'customer_name' => 'Laura Gomez',
            'notes' => 'Celebracion familiar',
            'deposit_amount' => 50000,
            'reserved_by' => $user->id,
        ]);
    }

    public function test_edit_form_keeps_current_table_visible_even_if_it_is_not_free(): void
    {
        $user = $this->adminUser();

        $reservedTable = RestaurantTable::query()->create([
            'name' => 'Mesa Asignada',
            'code' => 'MA-01',
            'area' => 'Principal',
            'capacity' => 4,
            'status' => 'reserved',
            'is_active' => true,
        ]);

        $otherFreeTable = RestaurantTable::query()->create([
            'name' => 'Mesa Libre Edit',
            'code' => 'ML-03',
            'area' => 'Principal',
            'capacity' => 4,
            'status' => 'free',
            'is_active' => true,
        ]);

        $reservation = Reservation::query()->create([
            'restaurant_table_id' => $reservedTable->id,
            'reserved_by' => $user->id,
            'customer_name' => 'Carlos Ruiz',
            'customer_phone' => '3009876543',
            'customer_email' => 'carlos@example.com',
            'reservation_at' => now()->addDay(),
            'party_size' => 3,
            'status' => 'confirmed',
            'notes' => 'Mesa con vista',
            'deposit_amount' => 25000,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('reservations.edit', $reservation));

        $response->assertOk();
        $response->assertSee($reservedTable->name);
        $response->assertSee($otherFreeTable->name);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();

        $adminRole = Role::query()->create([
            'name' => 'Admin',
            'description' => 'Administrador',
        ]);

        $user->roles()->attach($adminRole);

        return $user;
    }
}
