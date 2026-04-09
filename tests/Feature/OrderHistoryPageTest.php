<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\TableOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderHistoryPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_history_page_lists_saved_orders(): void
    {
        $user = User::factory()->create([
            'name' => 'Supervisor Salon',
        ]);

        $adminRole = Role::create([
            'name' => 'Admin',
            'description' => 'Administrador',
        ]);

        $user->roles()->attach($adminRole);

        $table = RestaurantTable::create([
            'name' => 'Mesa 12',
            'code' => 'M-12',
            'area' => 'Terraza',
            'capacity' => 4,
            'status' => 'free',
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'Limonada de coco',
            'description' => 'Bebida de prueba',
            'price' => 12,
            'stock' => 100,
            'tracks_stock' => false,
            'category' => 'Bebidas',
            'sku' => 'SKU-HISTORY-001',
            'product_type' => 'simple',
            'active' => true,
        ]);

        $order = TableOrder::create([
            'restaurant_table_id' => $table->id,
            'order_number' => 'PED-TEST-HISTORY-001',
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

        $response = $this
            ->actingAs($user)
            ->get(route('orders.history.index'));

        $response->assertOk();
        $response->assertViewIs('orders.history');
        $response->assertSee('Historial de pedidos');
        $response->assertSee('PED-TEST-HISTORY-001');
        $response->assertSee('Mesa 12');
        $response->assertSee('Ana Mesa');
    }
}
