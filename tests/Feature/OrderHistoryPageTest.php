<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\BoxMovement;
use App\Models\BoxSession;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Sale;
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
        $order->forceFill([
            'created_at' => '2026-06-22 10:00:00',
            'updated_at' => '2026-06-22 10:00:00',
        ])->save();

        $oldOrder = TableOrder::create([
            'restaurant_table_id' => $table->id,
            'order_number' => 'PED-TEST-HISTORY-OLD',
            'customer_name' => 'Pedido Viejo',
            'status' => 'paid',
            'opened_by_user_id' => $user->id,
        ]);
        $oldOrder->forceFill([
            'created_at' => '2026-06-01 10:00:00',
            'updated_at' => '2026-06-01 10:00:00',
        ])->save();

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

        $filteredResponse = $this
            ->actingAs($user)
            ->get(route('orders.history.index', [
                'date_from' => '2026-06-22',
                'date_to' => '2026-06-22',
            ]));

        $filteredResponse->assertOk();
        $filteredResponse->assertSee('Desde');
        $filteredResponse->assertSee('Hasta');
        $filteredResponse->assertSee('PED-TEST-HISTORY-001');
        $filteredResponse->assertDontSee('PED-TEST-HISTORY-OLD');
    }

    public function test_any_authenticated_user_can_edit_open_order_after_kitchen_ticket(): void
    {
        $user = User::factory()->create();
        $table = $this->createTable();
        $firstProduct = $this->createProduct('Sopa del dia', 12000, 'SOUP-EDIT');
        $secondProduct = $this->createProduct('Bandeja especial', 28000, 'MAIN-EDIT');

        $order = TableOrder::create([
            'restaurant_table_id' => $table->id,
            'order_number' => 'PED-EDIT-OPEN',
            'status' => 'open',
            'opened_by_user_id' => $user->id,
        ]);
        $order->items()->create([
            'product_id' => $firstProduct->id,
            'product_name' => $firstProduct->name,
            'unit_price' => $firstProduct->price,
            'quantity' => 1,
            'subtotal' => $firstProduct->price,
            'split_group' => 1,
        ]);
        $order->recalculateTotals();

        $this
            ->actingAs($user)
            ->put(route('orders.update', $order), [
                'notes' => 'Cambio despues de comanda',
                'items' => [
                    ['product_id' => $firstProduct->id, 'quantity' => 0],
                    ['product_id' => $secondProduct->id, 'quantity' => 2],
                ],
            ])
            ->assertRedirect(route('orders.show', $table));

        $this->assertDatabaseMissing('table_order_items', [
            'table_order_id' => $order->id,
            'product_id' => $firstProduct->id,
        ]);
        $this->assertDatabaseHas('table_order_items', [
            'table_order_id' => $order->id,
            'product_id' => $secondProduct->id,
            'quantity' => 2,
            'subtotal' => 56000,
        ]);
        $this->assertDatabaseHas('table_orders', [
            'id' => $order->id,
            'total' => 56000,
            'notes' => 'Cambio despues de comanda',
        ]);
    }

    public function test_paid_order_edit_updates_receipt_and_open_cash_session(): void
    {
        $user = User::factory()->create();
        $table = $this->createTable();
        $firstProduct = $this->createProduct('Plato cobrado', 20000, 'PAID-OLD');
        $secondProduct = $this->createProduct('Plato nuevo', 30000, 'PAID-NEW');
        $paymentMethod = PaymentMethod::create([
            'name' => 'Efectivo',
            'code' => 'CASH',
            'description' => 'Pago en efectivo',
            'active' => true,
        ]);
        $box = Box::create([
            'name' => 'Caja edicion',
            'code' => 'BOX-ORDER-EDIT',
            'user_id' => $user->id,
            'opening_balance' => 10000,
            'status' => 'open',
            'opened_at' => now(),
        ]);
        $session = BoxSession::create([
            'box_id' => $box->id,
            'user_id' => $user->id,
            'opening_balance' => 10000,
            'status' => 'open',
            'opened_at' => now(),
        ]);
        $order = TableOrder::create([
            'restaurant_table_id' => $table->id,
            'order_number' => 'PED-EDIT-PAID',
            'status' => 'paid',
            'opened_by_user_id' => $user->id,
            'subtotal' => 20000,
            'total' => 20000,
        ]);
        $order->items()->create([
            'product_id' => $firstProduct->id,
            'product_name' => $firstProduct->name,
            'unit_price' => $firstProduct->price,
            'quantity' => 1,
            'subtotal' => 20000,
            'split_group' => 1,
        ]);
        $sale = Sale::create([
            'user_id' => $user->id,
            'box_id' => $box->id,
            'table_order_id' => $order->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 20000,
            'total' => 20000,
        ]);
        $sale->items()->create([
            'product_id' => $firstProduct->id,
            'product_name' => $firstProduct->name,
            'quantity' => 1,
            'unit_price' => 20000,
            'subtotal' => 20000,
        ]);
        $payment = $sale->payments()->create([
            'payment_method_id' => $paymentMethod->id,
            'amount' => 20000,
            'received_amount' => 20000,
            'change_amount' => 0,
            'tip_amount' => 0,
            'status' => 'completed',
        ]);
        Invoice::create([
            'sale_id' => $sale->id,
            'invoice_number' => 'TKT-EDIT-PAID',
            'invoice_type' => Invoice::TYPE_TICKET,
            'provider' => 'local',
            'status' => 'issued',
            'issued_at' => now(),
        ]);
        BoxMovement::create([
            'box_id' => $box->id,
            'box_session_id' => $session->id,
            'sale_id' => $sale->id,
            'payment_id' => $payment->id,
            'user_id' => $user->id,
            'movement_type' => 'table_order_payment',
            'amount' => 20000,
            'balance_before' => 10000,
            'balance_after' => 30000,
            'description' => 'Cobro del pedido PED-EDIT-PAID',
            'occurred_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->put(route('orders.update', $order), [
                'items' => [
                    ['product_id' => $firstProduct->id, 'quantity' => 0],
                    ['product_id' => $secondProduct->id, 'quantity' => 1],
                ],
            ])
            ->assertRedirect(route('orders.show', $table));

        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'total' => 30000,
        ]);
        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'product_id' => $secondProduct->id,
            'subtotal' => 30000,
        ]);
        $this->assertDatabaseHas('payments', [
            'sale_id' => $sale->id,
            'amount' => 30000,
            'received_amount' => 30000,
        ]);
        $this->assertDatabaseHas('box_movements', [
            'sale_id' => $sale->id,
            'amount' => 30000,
            'balance_after' => 40000,
        ]);
    }

    public function test_paid_order_from_closed_cash_session_cannot_be_edited(): void
    {
        $user = User::factory()->create();
        $table = $this->createTable();
        $product = $this->createProduct('Producto cerrado', 15000, 'CLOSED-EDIT');
        $box = Box::create([
            'name' => 'Caja cerrada',
            'code' => 'BOX-CLOSED-EDIT',
            'opening_balance' => 10000,
            'status' => 'closed',
        ]);
        $session = BoxSession::create([
            'box_id' => $box->id,
            'user_id' => $user->id,
            'opening_balance' => 10000,
            'status' => 'closed',
            'opened_at' => now()->subHours(2),
            'closed_at' => now()->subHour(),
        ]);
        $order = TableOrder::create([
            'restaurant_table_id' => $table->id,
            'order_number' => 'PED-CLOSED-EDIT',
            'status' => 'paid',
            'opened_by_user_id' => $user->id,
            'subtotal' => 15000,
            'total' => 15000,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => $product->price,
            'quantity' => 1,
            'subtotal' => 15000,
            'split_group' => 1,
        ]);
        $sale = Sale::create([
            'user_id' => $user->id,
            'box_id' => $box->id,
            'table_order_id' => $order->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 15000,
            'total' => 15000,
        ]);
        BoxMovement::create([
            'box_id' => $box->id,
            'box_session_id' => $session->id,
            'sale_id' => $sale->id,
            'user_id' => $user->id,
            'movement_type' => 'table_order_payment',
            'amount' => 15000,
            'balance_before' => 10000,
            'balance_after' => 25000,
            'description' => 'Cobro cerrado',
            'occurred_at' => now()->subHour(),
        ]);

        $this
            ->actingAs($user)
            ->from(route('orders.edit', $order))
            ->put(route('orders.update', $order), [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ])
            ->assertSessionHasErrors('items');

        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'total' => 15000,
        ]);
    }

    private function createTable(): RestaurantTable
    {
        return RestaurantTable::create([
            'name' => 'Mesa Edit',
            'code' => 'M-EDIT-' . uniqid(),
            'area' => 'Salon',
            'capacity' => 4,
            'status' => 'occupied',
            'is_active' => true,
        ]);
    }

    private function createProduct(string $name, int $price, string $sku): Product
    {
        return Product::create([
            'name' => $name,
            'description' => 'Producto de prueba',
            'price' => $price,
            'stock' => 100,
            'tracks_stock' => false,
            'category' => 'Pruebas',
            'sku' => $sku,
            'product_type' => 'simple',
            'active' => true,
        ]);
    }
}
