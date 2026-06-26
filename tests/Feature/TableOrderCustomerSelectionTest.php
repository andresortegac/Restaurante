<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableOrderCustomerSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_store_creates_table_order_without_customer(): void
    {
        $user = User::factory()->create();
        $adminRole = Role::create([
            'name' => 'Admin',
            'description' => 'Administrador',
        ]);
        $user->roles()->attach($adminRole);

        $table = RestaurantTable::create([
            'name' => 'Mesa 3',
            'code' => 'M-03',
            'area' => 'Salon',
            'capacity' => 4,
            'status' => 'free',
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'Hamburguesa clasica',
            'description' => 'Prueba',
            'price' => 25,
            'stock' => 20,
            'tracks_stock' => false,
            'category' => 'Platos',
            'sku' => 'SKU-ORDER-CUSTOMER-1',
            'product_type' => 'simple',
            'active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(route('orders.store', $table), [
                'notes' => 'Sin tomate',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonStructure(['message', 'redirectUrl', 'printUrl']);

        $this->assertDatabaseHas('table_orders', [
            'restaurant_table_id' => $table->id,
            'customer_id' => null,
            'customer_name' => null,
            'status' => 'open',
        ]);
    }
}
