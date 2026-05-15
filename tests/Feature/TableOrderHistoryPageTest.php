<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\TableOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableOrderHistoryPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_history_index_lists_created_tables_with_access_button(): void
    {
        $user = $this->createAdminUser();

        $table = RestaurantTable::create([
            'name' => 'Mesa Jardin 3',
            'code' => 'M-03',
            'area' => 'Jardin',
            'capacity' => 6,
            'status' => 'free',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('tables.history.index'));

        $response->assertOk();
        $response->assertViewIs('tables.history.index');
        $response->assertSee('Historial de pedidos por mesa');
        $response->assertSee('Mesa Jardin 3');
        $response->assertSee(route('tables.history.show', $table), false);
    }

    public function test_table_history_show_lists_orders_for_selected_table(): void
    {
        $user = $this->createAdminUser();

        $table = RestaurantTable::create([
            'name' => 'Mesa 12',
            'code' => 'M-12',
            'area' => 'Terraza',
            'capacity' => 4,
            'status' => 'occupied',
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'Limonada de coco',
            'description' => 'Bebida de prueba',
            'price' => 12,
            'stock' => 100,
            'tracks_stock' => false,
            'category' => 'Bebidas',
            'sku' => 'SKU-TABLE-HISTORY-001',
            'product_type' => 'simple',
            'active' => true,
        ]);

        $order = TableOrder::create([
            'restaurant_table_id' => $table->id,
            'order_number' => 'PED-TABLE-HISTORY-001',
            'customer_name' => 'Ana Mesa',
            'status' => 'paid',
            'opened_by_user_id' => $user->id,
            'notes' => 'Sin azucar',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => 12,
            'quantity' => 2,
            'subtotal' => 24,
            'split_group' => 1,
        ]);

        $order->recalculateTotals();

        $historyResponse = $this
            ->actingAs($user)
            ->get(route('tables.history.show', $table));

        $historyResponse->assertOk();
        $historyResponse->assertViewIs('tables.history.show');
        $historyResponse->assertSee('PED-TABLE-HISTORY-001');
        $historyResponse->assertSee('Mesa 12');
        $historyResponse->assertSee('Ana Mesa');

        $detailResponse = $this
            ->actingAs($user)
            ->get(route('tables.show', $table));

        $detailResponse->assertOk();
        $detailResponse->assertDontSee('Cuando esta mesa tenga pedidos registrados, apareceran en este panel.');
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
