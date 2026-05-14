<?php

namespace Tests\Feature;

use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_hides_internal_code_and_store_generates_it(): void
    {
        $user = $this->createAdminUser();

        $createResponse = $this
            ->actingAs($user)
            ->get(route('tables.create'));

        $createResponse->assertOk();
        $createResponse->assertDontSee('Codigo interno');

        $storeResponse = $this
            ->actingAs($user)
            ->post(route('tables.store'), [
                'name' => 'Mesa Terraza 1',
                'area' => 'Terraza',
                'capacity' => 4,
                'status' => 'free',
                'notes' => 'Cerca de la entrada',
                'is_active' => 1,
            ]);

        $storeResponse->assertRedirect(route('tables.index'));

        $table = RestaurantTable::query()->firstOrFail();

        $this->assertSame('M-01', $table->code);
        $this->assertDatabaseHas('restaurant_tables', [
            'id' => $table->id,
            'name' => 'Mesa Terraza 1',
            'code' => 'M-01',
            'area' => 'Terraza',
            'capacity' => 4,
            'status' => 'free',
            'is_active' => true,
        ]);
    }

    public function test_edit_form_keeps_internal_code_field_visible(): void
    {
        $user = $this->createAdminUser();

        $table = RestaurantTable::query()->create([
            'name' => 'Mesa Salon 1',
            'code' => 'M-09',
            'area' => 'Salon',
            'capacity' => 4,
            'status' => 'free',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('tables.edit', $table));

        $response->assertOk();
        $response->assertSee('Codigo interno');
        $response->assertSee('value="M-09"', false);
    }

    private function createAdminUser(): User
    {
        $user = User::factory()->create();
        $adminRole = Role::create([
            'name' => 'Admin',
            'description' => 'Administrador',
        ]);

        $user->roles()->attach($adminRole);

        return $user;
    }
}
