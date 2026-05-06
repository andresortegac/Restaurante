<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_update_box_catalog(): void
    {
        $user = User::factory()->create();
        $adminRole = Role::create([
            'name' => 'Admin',
            'description' => 'Administrador',
        ]);
        $user->roles()->attach($adminRole);

        $this->actingAs($user)
            ->post(route('cash-management.store'), [
                'name' => 'Caja barra',
                'code' => 'bar-01',
            ])
            ->assertRedirect();

        $box = Box::query()->firstOrFail();

        $this->assertDatabaseHas('boxes', [
            'id' => $box->id,
            'name' => 'Caja barra',
            'code' => 'BAR-01',
            'status' => 'closed',
        ]);

        $this->actingAs($user)
            ->put(route('cash-management.update', $box), [
                'name' => 'Caja barra norte',
                'code' => 'BAR-02',
            ])
            ->assertRedirect(route('cash-management.show', $box));

        $this->assertDatabaseHas('boxes', [
            'id' => $box->id,
            'name' => 'Caja barra norte',
            'code' => 'BAR-02',
        ]);
    }

    public function test_admin_can_open_move_and_close_a_box_session(): void
    {
        $user = User::factory()->create();
        $adminRole = Role::create([
            'name' => 'Admin',
            'description' => 'Administrador',
        ]);
        $user->roles()->attach($adminRole);

        $box = Box::create([
            'name' => 'Caja central',
            'code' => 'BOX-CENTRAL',
            'status' => 'closed',
        ]);

        $this->actingAs($user)
            ->post(route('cash-management.open', $box), [
                'opening_balance' => 150,
                'opening_notes' => 'Inicio de turno',
            ])
            ->assertRedirect(route('cash-management.show', $box));

        $this->assertDatabaseHas('box_sessions', [
            'box_id' => $box->id,
            'user_id' => $user->id,
            'opening_balance' => '150.00',
            'status' => 'open',
        ]);

        $this->actingAs($user)
            ->post(route('cash-management.movements.store', $box), [
                'movement_type' => 'manual_income',
                'amount' => 25,
                'description' => 'Ingreso por fondo adicional',
            ])
            ->assertRedirect(route('cash-management.show', $box));

        $this->actingAs($user)
            ->post(route('cash-management.movements.store', $box), [
                'movement_type' => 'manual_expense',
                'amount' => 10,
                'description' => 'Compra de cambio',
            ])
            ->assertRedirect(route('cash-management.show', $box));

        $this->assertDatabaseHas('box_movements', [
            'box_id' => $box->id,
            'user_id' => $user->id,
            'movement_type' => 'manual_income',
            'amount' => '25.00',
        ]);

        $this->assertDatabaseHas('box_movements', [
            'box_id' => $box->id,
            'user_id' => $user->id,
            'movement_type' => 'manual_expense',
            'amount' => '-10.00',
        ]);

        $this->actingAs($user)
            ->post(route('cash-management.close', $box), [
                'counted_balance' => 170,
                'closing_notes' => 'Cierre correcto',
            ])
            ->assertRedirect(route('cash-management.show', $box));

        $this->assertDatabaseHas('box_sessions', [
            'box_id' => $box->id,
            'status' => 'closed',
            'counted_balance' => '170.00',
            'difference_amount' => '5.00',
        ]);

        $this->assertDatabaseHas('box_audit_logs', [
            'box_id' => $box->id,
            'user_id' => $user->id,
            'action' => 'box_opened',
        ]);

        $this->assertDatabaseHas('box_audit_logs', [
            'box_id' => $box->id,
            'user_id' => $user->id,
            'action' => 'box_closed',
        ]);
    }

    public function test_user_gets_a_form_error_when_trying_to_open_a_second_box(): void
    {
        $user = User::factory()->create();
        $adminRole = Role::create([
            'name' => 'Admin',
            'description' => 'Administrador',
        ]);
        $user->roles()->attach($adminRole);

        $firstBox = Box::create([
            'name' => 'Caja principal',
            'code' => 'BOX-001',
            'status' => 'closed',
        ]);

        $secondBox = Box::create([
            'name' => 'Caja barra',
            'code' => 'BOX-002',
            'status' => 'closed',
        ]);

        $this->actingAs($user)
            ->post(route('cash-management.open', $firstBox), [
                'opening_balance' => 100,
            ])
            ->assertRedirect(route('cash-management.show', $firstBox));

        $this->from(route('cash-management.show', $secondBox))
            ->actingAs($user)
            ->post(route('cash-management.open', $secondBox), [
                'opening_balance' => 50,
            ])
            ->assertRedirect(route('cash-management.show', $secondBox))
            ->assertSessionHasErrors([
                'opening_balance' => 'Ya tienes una sesion abierta en "Caja principal" y debes cerrarla antes de abrir "Caja barra".',
            ]);

        $this->assertDatabaseMissing('box_sessions', [
            'box_id' => $secondBox->id,
            'status' => 'open',
        ]);
    }
}
