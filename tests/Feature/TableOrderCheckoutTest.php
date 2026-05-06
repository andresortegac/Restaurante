<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\TableOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableOrderCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_creates_sale_payment_and_box_movement_before_freeing_the_table(): void
    {
        $user = User::factory()->create();
        $adminRole = Role::create([
            'name' => 'Admin',
            'description' => 'Administrador',
        ]);
        $user->roles()->attach($adminRole);

        $paymentMethod = PaymentMethod::create([
            'name' => 'Efectivo',
            'code' => 'CASH',
            'description' => 'Pago en efectivo',
            'active' => true,
        ]);

        $box = Box::create([
            'name' => 'Caja principal',
            'code' => 'BOX-TEST',
            'user_id' => $user->id,
            'opening_balance' => 100,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $table = RestaurantTable::create([
            'name' => 'Mesa 7',
            'code' => 'M-07',
            'area' => 'Salon',
            'capacity' => 4,
            'status' => 'occupied',
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'Bandeja especial',
            'description' => 'Plato de prueba',
            'price' => 10,
            'stock' => 50,
            'tracks_stock' => false,
            'category' => 'Platos',
            'sku' => 'SKU-TEST-001',
            'product_type' => 'simple',
            'active' => true,
        ]);

        $order = TableOrder::create([
            'restaurant_table_id' => $table->id,
            'order_number' => 'PED-TEST-001',
            'customer_name' => 'Carlos Mesa',
            'status' => 'open',
            'opened_by_user_id' => $user->id,
            'notes' => 'Sin cebolla',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => 10,
            'quantity' => 2,
            'subtotal' => 20,
            'split_group' => 1,
        ]);
        $order->recalculateTotals();

        $response = $this
            ->actingAs($user)
            ->post(route('orders.checkout.store', $order), [
                'payment_method_id' => $paymentMethod->id,
                'amount_received' => 30,
                'tip_amount' => 1.80,
                'reference' => 'Pago José Niño ñandú',
            ]);

        $response->assertOk();
        $response->assertViewIs('orders.print-bridge');

        $this->assertDatabaseHas('sales', [
            'table_order_id' => $order->id,
            'box_id' => $box->id,
            'user_id' => $user->id,
            'customer_name' => 'Carlos Mesa',
            'status' => 'completed',
            'subtotal' => '20.00',
            'tax_amount' => '3.20',
            'total' => '23.20',
        ]);

        $this->assertDatabaseHas('sale_items', [
            'product_id' => $product->id,
            'product_name' => 'Bandeja especial',
            'quantity' => 2,
            'unit_price' => '10.00',
            'subtotal' => '20.00',
        ]);

        $this->assertDatabaseHas('payments', [
            'payment_method_id' => $paymentMethod->id,
            'amount' => '23.20',
            'received_amount' => '30.00',
            'change_amount' => '5.00',
            'tip_amount' => '1.80',
            'reference' => 'Pago José Niño ñandú',
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('box_movements', [
            'box_id' => $box->id,
            'user_id' => $user->id,
            'movement_type' => 'table_order_payment',
            'amount' => '25.00',
            'balance_before' => '100.00',
            'balance_after' => '125.00',
        ]);

        $this->assertDatabaseHas('table_orders', [
            'id' => $order->id,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('restaurant_tables', [
            'id' => $table->id,
            'status' => 'free',
        ]);

        $saleId = \App\Models\Sale::query()->value('id');

        $this->assertNotNull($saleId);
        $this->assertDatabaseHas('invoices', [
            'sale_id' => $saleId,
            'invoice_type' => 'ticket',
            'provider' => 'local',
            'status' => 'issued',
        ]);
    }
}
