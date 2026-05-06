<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableOrderMenuVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_hidden_category_products_cannot_be_added_to_a_table_order(): void
    {
        $user = $this->createAdminUser();

        $category = ProductCategory::create([
            'name' => 'Ocultos',
            'slug' => 'ocultos',
            'description' => 'Categoria no visible para meseros',
            'sort_order' => 1,
            'is_active' => false,
        ]);

        $product = Product::create([
            'name' => 'Producto oculto',
            'description' => 'No deberia poder pedirse',
            'price' => 14,
            'stock' => 0,
            'tracks_stock' => false,
            'category' => 'Ocultos',
            'category_id' => $category->id,
            'sku' => 'HIDE-001',
            'product_type' => 'simple',
            'sort_order' => 1,
            'active' => true,
        ]);

        $table = RestaurantTable::create([
            'name' => 'Mesa 12',
            'code' => 'M-12',
            'area' => 'Salon',
            'capacity' => 4,
            'status' => 'free',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(route('orders.store', $table), [
                'customer_id' => null,
                'notes' => 'Prueba de visibilidad',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 1,
                    ],
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('items');

        $this->assertDatabaseCount('table_orders', 0);
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
