<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_product_with_image(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $adminRole = Role::create([
            'name' => 'Admin',
            'description' => 'Administrador',
        ]);
        $user->roles()->attach($adminRole);

        $response = $this
            ->actingAs($user)
            ->post(route('products.menu.store'), [
                'name' => 'Pizza Margarita',
                'description' => 'Pizza de prueba',
                'sku' => 'SKU-IMG-001',
                'category_name' => 'Pizzas',
                'price' => 32.50,
                'tracks_stock' => 0,
                'active' => 1,
                'image' => UploadedFile::fake()->image('pizza.jpg'),
            ]);

        $response->assertRedirect(route('products.menu.index'));

        $this->assertDatabaseHas('products', [
            'name' => 'Pizza Margarita',
            'sku' => 'SKU-IMG-001',
        ]);

        $imagePath = (string) \App\Models\Product::query()->value('image_path');

        $this->assertNotSame('', $imagePath);
        Storage::disk('public')->assertExists($imagePath);
    }
}
