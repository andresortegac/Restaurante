<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\BoxAuditLog;
use App\Models\BoxMovement;
use App\Models\BoxSession;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
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
        $cashMethod = PaymentMethod::create([
            'name' => 'Efectivo',
            'code' => 'CASH',
            'description' => 'Pago en efectivo',
            'active' => true,
        ]);
        $transferMethod = PaymentMethod::create([
            'name' => 'Transferencia Bancaria',
            'code' => 'TRANSFER',
            'description' => 'Transferencia electronica',
            'active' => true,
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
                'payment_method_id' => $cashMethod->id,
                'amount' => 25,
                'description' => 'Ingreso por fondo adicional',
            ])
            ->assertRedirect(route('cash-management.show', $box));

        $this->actingAs($user)
            ->post(route('cash-management.movements.store', $box), [
                'movement_type' => 'manual_income',
                'payment_method_id' => $transferMethod->id,
                'amount' => 40,
                'description' => 'Ingreso por transferencia',
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
            'description' => 'Ingreso por fondo adicional | Metodo Efectivo',
        ]);

        $this->assertDatabaseHas('box_movements', [
            'box_id' => $box->id,
            'user_id' => $user->id,
            'movement_type' => 'manual_income',
            'amount' => '0.00',
            'description' => 'Ingreso por transferencia | Metodo Transferencia Bancaria',
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
        PaymentMethod::create([
            'name' => 'Efectivo',
            'code' => 'CASH',
            'description' => 'Pago en efectivo',
            'active' => true,
        ]);
        $transferMethod = PaymentMethod::create([
            'name' => 'Transferencia Bancaria',
            'code' => 'TRANSFER',
            'description' => 'Transferencia electronica',
            'active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('cash-management.open', $box), [
                'opening_balance' => 80,
            ])
            ->assertRedirect(route('cash-management.show', $box));

        $this->actingAs($user)
            ->get(route('cash-management.index'))
            ->assertOk()
            ->assertDontSee('Movimiento manual')
            ->assertDontSee('Sesiones acumuladas');

        $response = $this->actingAs($user)
            ->get(route('cash-management.show', $box));

        $response->assertOk();
        $response->assertSee('Movimiento manual');
        $response->assertSee('Ingreso manual');
        $response->assertSee('Egreso manual');
        $response->assertSee('Metodo de pago');
        $response->assertSee('Efectivo');
        $response->assertSee('Transferencia Bancaria');

        $this->actingAs($user)
            ->get(route('cash-management.movements.create', $box))
            ->assertRedirect(route('cash-management.show', ['box' => $box, 'panel' => 'movement']) . '#manual-movement');

        $session = BoxSession::query()
            ->where('box_id', $box->id)
            ->where('status', 'open')
            ->firstOrFail();
        $sale = Sale::create([
            'user_id' => $user->id,
            'box_id' => $box->id,
            'subtotal' => 70000,
            'tax_amount' => 0,
            'total' => 70000,
            'status' => 'completed',
            'payment_status' => 'paid',
        ]);
        $payment = Payment::create([
            'sale_id' => $sale->id,
            'payment_method_id' => $transferMethod->id,
            'amount' => 70000,
            'received_amount' => 70000,
            'change_amount' => 0,
            'tip_amount' => 0,
            'status' => 'completed',
        ]);
        $movement = BoxMovement::create([
            'box_id' => $box->id,
            'box_session_id' => $session->id,
            'sale_id' => $sale->id,
            'payment_id' => $payment->id,
            'user_id' => $user->id,
            'movement_type' => 'table_order_payment',
            'amount' => 0,
            'balance_before' => 80,
            'balance_after' => 80,
            'description' => 'Cobro por transferencia',
            'occurred_at' => now(),
        ]);
        BoxAuditLog::create([
            'box_id' => $box->id,
            'box_session_id' => $session->id,
            'user_id' => $user->id,
            'action' => 'table_order_payment',
            'description' => 'Cobro por transferencia',
            'metadata' => [
                'movement_id' => $movement->id,
                'payment_id' => $payment->id,
                'payment_method_id' => $transferMethod->id,
                'amount' => 0,
            ],
            'occurred_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('cash-management.show', ['box' => $box, 'panel' => 'close']))
            ->assertOk()
            ->assertSee('Cierre diario')
            ->assertSee('Volver')
            ->assertSee(route('cash-management.index'))
            ->assertSee('Movimiento manual')
            ->assertSee('Guardar movimiento')
            ->assertSee('Transferencias bancarias')
            ->assertSee('$70,000')
            ->assertSee('Saldo esperado ventas en efectivo')
            ->assertSee('closingDifferencePreview')
            ->assertDontSee('Entradas por metodo de pago')
            ->assertDontSee('Imprimir detallado')
            ->assertDontSee('Si el valor contado coincide con el saldo esperado');

        $this->actingAs($user)
            ->get(route('cash-management.show', ['box' => $box, 'panel' => 'movement']))
            ->assertOk()
            ->assertSee('Movimiento manual')
            ->assertSee('Cierre diario');
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
        $transferMethod = PaymentMethod::create([
            'name' => 'Transferencia Bancaria',
            'code' => 'TRANSFER',
            'description' => 'Transferencia electronica',
            'active' => true,
        ]);
        $transferSale = Sale::create([
            'user_id' => $user->id,
            'box_id' => $box->id,
            'customer_name' => 'Cliente Transferencia',
            'subtotal' => 95,
            'tax_amount' => 0,
            'total' => 95,
            'status' => 'completed',
            'payment_status' => 'paid',
        ]);
        $transferPayment = Payment::create([
            'sale_id' => $transferSale->id,
            'payment_method_id' => $transferMethod->id,
            'amount' => 95,
            'received_amount' => 95,
            'change_amount' => 0,
            'tip_amount' => 0,
            'status' => 'completed',
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
            'box_session_id' => $morningSession->id,
            'sale_id' => $transferSale->id,
            'payment_id' => $transferPayment->id,
            'user_id' => $user->id,
            'movement_type' => 'table_order_payment',
            'amount' => 0,
            'balance_before' => 220,
            'balance_after' => 220,
            'description' => 'Cobro por transferencia',
            'occurred_at' => Carbon::parse('2026-06-22 09:30:00'),
        ]);

        BoxMovement::create([
            'box_id' => $box->id,
            'box_session_id' => $morningSession->id,
            'user_id' => $user->id,
            'movement_type' => 'manual_income',
            'amount' => 50,
            'balance_before' => 220,
            'balance_after' => 270,
            'description' => 'Ingreso manual sin recibo',
            'occurred_at' => Carbon::parse('2026-06-22 10:00:00'),
        ]);

        BoxMovement::create([
            'box_id' => $box->id,
            'box_session_id' => $morningSession->id,
            'user_id' => $user->id,
            'movement_type' => 'manual_expense',
            'amount' => -20,
            'balance_before' => 270,
            'balance_after' => 250,
            'description' => 'Egreso manual de prueba',
            'occurred_at' => Carbon::parse('2026-06-22 10:30:00'),
        ]);

        BoxMovement::create([
            'box_id' => $box->id,
            'box_session_id' => $morningSession->id,
            'user_id' => $user->id,
            'movement_type' => 'customer_balance_payment',
            'amount' => 30,
            'balance_before' => 250,
            'balance_after' => 280,
            'description' => 'Pago saldo cliente',
            'occurred_at' => Carbon::parse('2026-06-22 10:45:00'),
        ]);

        BoxMovement::create([
            'box_id' => $box->id,
            'box_session_id' => $nightSession->id,
            'user_id' => $user->id,
            'movement_type' => 'manual_income',
            'amount' => 80,
            'balance_before' => 150,
            'balance_after' => 230,
            'description' => 'Ingreso de otra sesion',
            'occurred_at' => Carbon::parse('2026-06-22 20:00:00'),
        ]);

        $this->actingAs($user)
            ->get(route('cash-management.history'))
            ->assertOk()
            ->assertSee('Historial de cierres')
            ->assertSee('Caja principal')
            ->assertSee('Valor transferencia')
            ->assertSee('$95')
            ->assertSee('5 movimientos')
            ->assertDontSee('<th>Caja</th>', false)
            ->assertDontSee('<th>Responsable</th>', false)
            ->assertSee('Tirilla general')
            ->assertSee('Ver movimientos');

        $this->actingAs($user)
            ->get(route('cash-management.history.sessions.print', $morningSession))
            ->assertOk()
            ->assertSee('SOLOMO & POMO', false)
            ->assertDontSee('Laravel')
            ->assertSee('Tirilla general de cierre')
            ->assertSee('Cierre')
            ->assertSee('22/06/2026 11:30')
            ->assertSee('Base inicial')
            ->assertSee('Entradas en efectivo')
            ->assertSee('Salidas en efectivo')
            ->assertSee('Saldo esperado en efectivo')
            ->assertSee('Valor contado en efectivo')
            ->assertSee('Diferencia de efectivo')
            ->assertSee('Transferencias informadas')
            ->assertSee('Observaciones')
            ->assertSee('Sin observaciones')
            ->assertSee('$95')
            ->assertDontSee('Detalle de movimientos')
            ->assertDontSee('Entradas por metodo de pago');

        $this->actingAs($user)
            ->get(route('cash-management.history.sessions.show', [
                'session' => $morningSession,
            ]))
            ->assertOk()
            ->assertSee('Movimientos para imprimir')
            ->assertSee('Metodo de pago')
            ->assertSee('Efectivo')
            ->assertSee('Transferencia')
            ->assertDontSee('for="date_from"', false)
            ->assertDontSee('for="date_to"', false)
            ->assertSee('Transferencias')
            ->assertSee('$95')
            ->assertSee('Cuadre de caja')
            ->assertSee('Saldo esperado en efectivo')
            ->assertDontSee('$95 transferencias')
            ->assertDontSee('$50 ingresos manuales')
            ->assertDontSee('$20 egresos manuales')
            ->assertDontSee('Imprimir detallado')
            ->assertSee('TKT-202606-000123')
            ->assertSee('Cliente Factura')
            ->assertSee('Ingreso manual sin recibo')
            ->assertSee('Egreso manual de prueba')
            ->assertSee('Pago de saldo del cliente')
            ->assertDontSee('customer balance payment')
            ->assertDontSee('customer_balance_payment')
            ->assertSee('Tirilla')
            ->assertDontSee('Ingreso de otra sesion');

        $this->actingAs($user)
            ->get(route('cash-management.history.sessions.show', [
                'session' => $morningSession,
                'payment_method' => 'transfer',
            ]))
            ->assertOk()
            ->assertSee('Cliente Transferencia')
            ->assertSee('Transferencia Bancaria')
            ->assertDontSee('Cliente Factura')
            ->assertDontSee('Ingreso manual sin recibo')
            ->assertDontSee('Egreso manual de prueba');

        $this->actingAs($user)
            ->get(route('cash-management.history.sessions.show', [
                'session' => $morningSession,
                'payment_method' => 'cash',
            ]))
            ->assertOk()
            ->assertSee('Cliente Factura')
            ->assertSee('Ingreso manual sin recibo')
            ->assertSee('Egreso manual de prueba')
            ->assertSee('Metodo de pago: Efectivo')
            ->assertSee('<strong class="text-danger">-$20</strong>', false)
            ->assertDontSee('Cliente Transferencia')
            ->assertDontSee('Transferencia Bancaria');

        $manualMovement = BoxMovement::query()
            ->where('description', 'Ingreso manual sin recibo')
            ->firstOrFail();

        $this->actingAs($user)
            ->get(route('cash-management.history.movements.print', $manualMovement))
            ->assertOk()
            ->assertSee('SOLOMO & POMO', false)
            ->assertSee('Tirilla de movimiento')
            ->assertSee('Ingreso manual sin recibo');
    }
}
