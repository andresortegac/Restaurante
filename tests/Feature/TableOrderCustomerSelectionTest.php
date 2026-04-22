<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableOrderCustomerSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_store_uses_selected_customer_and_defaults_to_without_customer(): void
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

        $customer = Customer::create([
            'name' => 'Pedro Perez',
            'document_number' => 'CC-9001',
            'phone' => '3010001111',
            'email' => 'pedro@example.com',
            'is_active' => true,
        ]);

        $withCustomerResponse = $this
            ->actingAs($user)
            ->postJson(route('orders.store', $table), [
                'customer_id' => $customer->id,
                'notes' => 'Sin tomate',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ]);

        $withCustomerResponse->assertOk();
        $withCustomerResponse->assertJsonStructure(['message', 'redirectUrl', 'printUrl']);

        $this->assertDatabaseHas('table_orders', [
            'restaurant_table_id' => $table->id,
            'customer_id' => $customer->id,
            'customer_name' => 'Pedro Perez',
            'status' => 'open',
        ]);

        $secondTable = RestaurantTable::create([
            'name' => 'Mesa 4',
            'code' => 'M-04',
            'area' => 'Salon',
            'capacity' => 4,
            'status' => 'free',
            'is_active' => true,
        ]);

        $withoutCustomerResponse = $this
            ->actingAs($user)
            ->postJson(route('orders.store', $secondTable), [
                'customer_id' => '',
                'notes' => 'Sin cliente asociado',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ]);

        $withoutCustomerResponse->assertOk();

        $this->assertDatabaseHas('table_orders', [
            'restaurant_table_id' => $secondTable->id,
            'customer_id' => null,
            'customer_name' => null,
            'status' => 'open',
        ]);
    }
}
