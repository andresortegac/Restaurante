<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableOrderShowPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_waiter_order_page_renders_graphical_menu_by_category(): void
    {
        $user = $this->createAdminUser();

        $category = ProductCategory::create([
            'name' => 'Bebidas',
            'slug' => 'bebidas',
            'description' => 'Refrescos y bebidas frias',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Product::create([
            'name' => 'Limonada de coco',
            'description' => 'Bebida de prueba',
            'price' => 12,
            'stock' => 0,
            'tracks_stock' => false,
            'category' => 'Bebidas',
            'category_id' => $category->id,
            'sku' => 'SHOW-001',
            'product_type' => 'simple',
            'sort_order' => 1,
            'active' => true,
        ]);

        $table = RestaurantTable::create([
            'name' => 'Mesa 3',
            'code' => 'M-03',
            'area' => 'Terraza',
            'capacity' => 4,
            'status' => 'free',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('orders.show', $table));

        $response->assertOk();
        $response->assertSee('Comanda visual');
        $response->assertSee('Todo el menu');
        $response->assertSee('Limonada de coco');
        $response->assertSee('Bebidas');
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
