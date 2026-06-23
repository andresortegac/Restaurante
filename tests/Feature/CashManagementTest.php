<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\BoxMovement;
use App\Models\BoxSession;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Carbon\Carbon;
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
                'description' => 'Caja secundaria de barra',
            ])
            ->assertRedirect();

        $box = Box::query()->firstOrFail();

        $this->assertDatabaseHas('boxes', [
            'id' => $box->id,
            'name' => 'Caja barra',
            'description' => 'Caja secundaria de barra',
            'status' => 'closed',
        ]);

        $this->actingAs($user)
            ->put(route('cash-management.update', $box), [
                'name' => 'Caja barra norte',
                'description' => 'Caja fija del costado norte',
            ])
            ->assertRedirect(route('cash-management.show', $box));

        $this->assertDatabaseHas('boxes', [
            'id' => $box->id,
            'name' => 'Caja barra norte',
            'description' => 'Caja fija del costado norte',
        ]);
    }

    public function test_admin_can_create_box_without_operational_description(): void
    {
        $user = User::factory()->create();
        $adminRole = Role::create([
            'name' => 'Admin',
            'description' => 'Administrador',
        ]);
        $user->roles()->attach($adminRole);

        $this->actingAs($user)
            ->post(route('cash-management.store'), [
                'name' => 'Caja rapida',
                'description' => '',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('boxes', [
            'name' => 'Caja rapida',
            'description' => null,
            'status' => 'closed',
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

    public function test_admin_sees_manual_movement_form_in_box_detail(): void
    {
        $user = User::factory()->create();
        $adminRole = Role::create([
            'name' => 'Admin',
            'description' => 'Administrador',
        ]);
        $user->roles()->attach($adminRole);

        $box = Box::create([
            'name' => 'Caja auxiliar',
            'code' => 'BOX-AUX',
            'status' => 'closed',
        ]);

        $this->actingAs($user)
            ->post(route('cash-management.open', $box), [
                'opening_balance' => 80,
            ])
            ->assertRedirect(route('cash-management.show', $box));

        $response = $this->actingAs($user)
            ->get(route('cash-management.show', $box));

        $response->assertOk();
        $response->assertSee('Movimiento manual');
        $response->assertSee('Ingreso manual');
        $response->assertSee('Egreso manual');

        $this->actingAs($user)
            ->get(route('cash-management.movements.create', $box))
            ->assertRedirect(route('cash-management.show', ['box' => $box, 'panel' => 'movement']) . '#manual-movement');

        $this->actingAs($user)
            ->get(route('cash-management.show', ['box' => $box, 'panel' => 'close']))
            ->assertOk()
            ->assertSee('Cierre diario')
            ->assertDontSee('Movimiento manual');

        $this->actingAs($user)
            ->get(route('cash-management.show', ['box' => $box, 'panel' => 'movement']))
            ->assertOk()
            ->assertSee('Movimiento manual')
            ->assertDontSee('Cierre diario');
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

    public function test_cash_history_groups_closures_and_filters_session_income_movements(): void
    {
        $user = User::factory()->create();
        $adminRole = Role::create([
            'name' => 'Admin',
            'description' => 'Administrador',
        ]);
        $user->roles()->attach($adminRole);

        $box = Box::create([
            'name' => 'Caja principal',
            'code' => 'BOX-001',
            'status' => 'closed',
        ]);

        $morningSession = BoxSession::create([
            'box_id' => $box->id,
            'user_id' => $user->id,
            'opening_balance' => 100,
            'status' => 'closed',
            'counted_balance' => 300,
            'difference_amount' => 0,
            'closed_by_user_id' => $user->id,
            'opened_at' => Carbon::parse('2026-06-22 07:00:00'),
            'closed_at' => Carbon::parse('2026-06-22 11:30:00'),
        ]);

        $nightSession = BoxSession::create([
            'box_id' => $box->id,
            'user_id' => $user->id,
            'opening_balance' => 150,
            'status' => 'closed',
            'counted_balance' => 500,
            'difference_amount' => 0,
            'closed_by_user_id' => $user->id,
            'opened_at' => Carbon::parse('2026-06-22 18:00:00'),
            'closed_at' => Carbon::parse('2026-06-22 22:30:00'),
        ]);

        $sale = Sale::create([
            'user_id' => $user->id,
            'box_id' => $box->id,
            'customer_name' => 'Cliente Factura',
            'subtotal' => 120,
            'tax_amount' => 0,
            'total' => 120,
            'status' => 'completed',
            'payment_status' => 'paid',
        ]);

        Invoice::create([
            'sale_id' => $sale->id,
            'invoice_number' => 'TKT-202606-000123',
            'invoice_type' => Invoice::TYPE_TICKET,
            'provider' => 'local',
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        BoxMovement::create([
            'box_id' => $box->id,
            'box_session_id' => $morningSession->id,
            'sale_id' => $sale->id,
            'user_id' => $user->id,
            'movement_type' => 'table_order_payment',
            'amount' => 120,
            'balance_before' => 100,
            'balance_after' => 220,
            'description' => 'Cobro de mesa',
            'occurred_at' => Carbon::parse('2026-06-22 09:00:00'),
        ]);

        BoxMovement::create([
            'box_id' => $box->id,
            'box_session_id' => $nightSession->id,
            'user_id' => $user->id,
            'movement_type' => 'manual_income',
            'amount' => 80,
            'balance_before' => 150,
            'balance_after' => 230,
            'description' => 'Ingreso de noche',
            'occurred_at' => Carbon::parse('2026-06-22 20:00:00'),
        ]);

        $this->actingAs($user)
            ->get(route('cash-management.history'))
            ->assertOk()
            ->assertSee('Cierre de la mañana')
            ->assertSee('Cierre de la noche')
            ->assertSee('Ver movimientos');

        $this->actingAs($user)
            ->get(route('cash-management.history.sessions.show', [
                'session' => $morningSession,
                'search' => 'TKT-202606-000123',
            ]))
            ->assertOk()
            ->assertSee('Ingresos del cierre')
            ->assertSee('TKT-202606-000123')
            ->assertSee('Cliente Factura')
            ->assertDontSee('Ingreso de noche');
    }
}
