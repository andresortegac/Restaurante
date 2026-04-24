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
}
