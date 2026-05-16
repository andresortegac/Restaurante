<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\RestaurantTable;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Sale;
use App\Models\TableOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_displays_live_metrics_and_access_information(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 15, 14, 45, 0));

        $user = User::factory()->create([
            'name' => 'Admin Dashboard',
            'email' => 'admin.dashboard@example.com',
            'current_login_at' => Carbon::create(2026, 5, 15, 14, 30, 0),
            'previous_login_at' => Carbon::create(2026, 5, 14, 21, 10, 0),
        ]);

        $adminRole = Role::create([
            'name' => 'Admin',
            'description' => 'Administrador',
        ]);

        $user->roles()->attach($adminRole);

        $box = Box::create([
            'name' => 'Caja principal',
            'code' => 'BOX-DASH-01',
            'user_id' => $user->id,
            'opening_balance' => 0,
            'status' => 'open',
            'opened_at' => Carbon::create(2026, 5, 15, 8, 0, 0),
        ]);

        $occupiedTable = RestaurantTable::create([
            'name' => 'Mesa 1',
            'code' => 'M-01',
            'area' => 'Salon',
            'capacity' => 4,
            'status' => 'occupied',
            'is_active' => true,
        ]);

        RestaurantTable::create([
            'name' => 'Mesa 2',
            'code' => 'M-02',
            'area' => 'Terraza',
            'capacity' => 4,
            'status' => 'free',
            'is_active' => true,
        ]);

        Customer::create([
            'name' => 'Cliente Activo',
            'email' => 'cliente@example.com',
            'is_active' => true,
        ]);

        Customer::create([
            'name' => 'Cliente Inactivo',
            'email' => 'cliente2@example.com',
            'is_active' => false,
        ]);

        $todayOrder = TableOrder::create([
            'restaurant_table_id' => $occupiedTable->id,
            'order_number' => 'PED-DASH-001',
            'status' => 'open',
            'opened_by_user_id' => $user->id,
        ]);
        $todayOrder->forceFill([
            'created_at' => Carbon::create(2026, 5, 15, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 5, 15, 9, 0, 0),
        ])->saveQuietly();

        $olderOrder = TableOrder::create([
            'restaurant_table_id' => $occupiedTable->id,
            'order_number' => 'PED-DASH-002',
            'status' => 'paid',
            'opened_by_user_id' => $user->id,
        ]);
        $olderOrder->forceFill([
            'created_at' => Carbon::create(2026, 5, 10, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 5, 10, 9, 0, 0),
        ])->saveQuietly();

        Delivery::create([
            'assigned_user_id' => $user->id,
            'delivery_number' => 'DOM-DASH-001',
            'customer_name' => 'Cliente Domicilio',
            'customer_phone' => '3001234567',
            'delivery_address' => 'Calle 1 # 2-3',
            'order_total' => 40,
            'delivery_fee' => 5,
            'total_charge' => 45,
            'status' => 'pending',
        ]);

        Reservation::create([
            'restaurant_table_id' => $occupiedTable->id,
            'reserved_by' => $user->id,
            'customer_name' => 'Cliente Reserva',
            'customer_phone' => '3000000000',
            'reservation_at' => Carbon::create(2026, 5, 15, 20, 0, 0),
            'party_size' => 2,
            'status' => 'confirmed',
        ]);

        $todaySale = Sale::create([
            'user_id' => $user->id,
            'box_id' => $box->id,
            'subtotal' => 100,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total' => 100,
            'status' => 'completed',
        ]);
        $todaySale->forceFill([
            'created_at' => Carbon::create(2026, 5, 15, 11, 0, 0),
            'updated_at' => Carbon::create(2026, 5, 15, 11, 0, 0),
        ])->saveQuietly();

        $monthlySale = Sale::create([
            'user_id' => $user->id,
            'box_id' => $box->id,
            'subtotal' => 250,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total' => 250,
            'status' => 'completed',
        ]);
        $monthlySale->forceFill([
            'created_at' => Carbon::create(2026, 5, 5, 10, 0, 0),
            'updated_at' => Carbon::create(2026, 5, 5, 10, 0, 0),
        ])->saveQuietly();

        $response = $this
            ->actingAs($user)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Pedidos de mesa hoy');
        $response->assertSee('Domicilios hoy');
        $response->assertSee('Reservas hoy');
        $response->assertSee('Mesas disponibles');
        $response->assertSee('Ingresos mensuales');
        $response->assertSee('Acceso actual');
        $response->assertSee('15/05/2026 14:30');
        $response->assertSee('Acceso anterior');
        $response->assertSee('14/05/2026 21:10');
        $response->assertSee('$100.00');
        $response->assertSee('$350.00');
        $response->assertDontSee('Permisos Autorizados');
        $response->assertDontSee('Estado del Sistema');

        Carbon::setTestNow();
    }

    public function test_dashboard_hides_financial_stats_for_non_admin_profiles(): void
    {
        $user = User::factory()->create();
        $role = Role::create([
            'name' => 'Mesero',
            'description' => 'Operacion',
        ]);

        $user->roles()->attach($role);

        $response = $this
            ->actingAs($user)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('Ventas Hoy');
        $response->assertDontSee('Ingresos mensuales');
    }

    public function test_login_updates_current_and_previous_access_timestamps(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 15, 18, 20, 0));

        $user = User::factory()->create([
            'email' => 'access@example.com',
            'password' => bcrypt('password'),
            'current_login_at' => Carbon::create(2026, 5, 10, 8, 15, 0),
        ]);

        $response = $this->post(route('login'), [
            'email' => 'access@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));

        $user->refresh();

        $this->assertSame('2026-05-15 18:20:00', $user->current_login_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-10 08:15:00', $user->previous_login_at?->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }
}
