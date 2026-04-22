<?php

namespace Tests\Feature;

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
}
