<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_update_category_with_visual_order(): void
    {
        $user = $this->createAdminUser();

        $createResponse = $this
            ->actingAs($user)
            ->post(route('products.categories.store'), [
                'name' => 'Entradas',
                'description' => 'Platos para compartir',
                'sort_order' => 3,
                'is_active' => 1,
            ]);

        $createResponse->assertRedirect(route('products.menu.index'));

        $category = ProductCategory::query()->firstOrFail();

        $this->assertDatabaseHas('product_categories', [
            'id' => $category->id,
            'name' => 'Entradas',
            'slug' => 'entradas',
            'sort_order' => 3,
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'Patacon mixto',
            'description' => 'Producto de prueba',
            'price' => 18,
            'stock' => 0,
            'tracks_stock' => false,
            'category' => 'Entradas',
            'category_id' => $category->id,
            'sku' => 'CAT-TEST-001',
            'product_type' => 'simple',
            'sort_order' => 1,
            'active' => true,
        ]);

        $updateResponse = $this
            ->actingAs($user)
            ->put(route('products.categories.update', $category), [
                'name' => 'Entradas frias',
                'description' => 'Opciones de apertura del menu',
                'sort_order' => 1,
                'is_active' => 0,
            ]);

        $updateResponse->assertRedirect(route('products.menu.index'));

        $this->assertDatabaseHas('product_categories', [
            'id' => $category->id,
            'name' => 'Entradas frias',
            'slug' => 'entradas-frias',
            'sort_order' => 1,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'category_id' => $category->id,
            'category' => 'Entradas frias',
        ]);
    }

    public function test_category_with_products_cannot_be_deleted(): void
    {
        $user = $this->createAdminUser();

        $category = ProductCategory::create([
            'name' => 'Bebidas',
            'slug' => 'bebidas',
            'description' => 'Categoria de prueba',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Product::create([
            'name' => 'Limonada',
            'description' => 'Producto de prueba',
            'price' => 9,
            'stock' => 0,
            'tracks_stock' => false,
            'category' => 'Bebidas',
            'category_id' => $category->id,
            'sku' => 'CAT-TEST-002',
            'product_type' => 'simple',
            'sort_order' => 1,
            'active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->delete(route('products.categories.destroy', $category));

        $response->assertRedirect(route('products.menu.index'));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('product_categories', [
            'id' => $category->id,
        ]);
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
