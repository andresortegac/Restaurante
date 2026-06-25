<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\PaymentMethod;
use App\Models\ProductCategory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_database_seeder_creates_only_admin_user_without_boxes_products_or_categories(): void
    {
        $this->seed();

        $this->assertSame(0, Box::query()->count());
        $this->assertSame(0, Product::query()->count());
        $this->assertSame(0, ProductCategory::query()->count());
        $this->assertSame(1, User::query()->count());
        $this->assertDatabaseHas('users', [
            'email' => 'admin@restaurante.com',
        ]);
        $this->assertDatabaseMissing('users', [
            'email' => 'cajero@restaurante.com',
        ]);
        $this->assertDatabaseMissing('users', [
            'email' => 'mesero@restaurante.com',
        ]);
        $this->assertGreaterThan(0, PaymentMethod::query()->count());
        $this->assertDatabaseHas('payment_methods', [
            'code' => 'CASH',
            'name' => 'Efectivo',
            'active' => true,
        ]);
        $this->assertDatabaseHas('payment_methods', [
            'code' => 'TRANSFER',
            'name' => 'Transferencia Bancaria',
            'active' => true,
        ]);
        $this->assertDatabaseHas('payment_methods', [
            'code' => 'DIGITAL_WALLET',
            'name' => 'Nequi',
            'active' => false,
        ]);
        $this->assertDatabaseHas('payment_methods', [
            'code' => 'DEBIT_CARD',
            'name' => 'Tarjeta de Debito',
            'active' => false,
        ]);
    }
}
