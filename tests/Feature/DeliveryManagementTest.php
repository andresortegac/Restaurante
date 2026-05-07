<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryDriver;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeliveryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_prefills_current_scheduled_datetime(): void
    {
        $now = now()->seconds(0);
        $this->travelTo($now);

        $user = User::factory()->create();
        $adminRole = Role::create([
            'name' => 'Admin',
            'description' => 'Administrador',
        ]);
        $user->roles()->attach($adminRole);

        $response = $this
            ->actingAs($user)
            ->get(route('deliveries.create'));

        $response->assertOk();
        $response->assertSee('value="' . $now->format('Y-m-d\TH:i') . '"', false);

        $this->travelBack();
    }

    public function test_admin_can_create_complete_and_list_deliveries(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $adminRole = Role::create([
            'name' => 'Admin',
            'description' => 'Administrador',
        ]);
        $user->roles()->attach($adminRole);

        $customer = Customer::create([
            'name' => 'Cliente Domicilio',
            'phone' => '3001112233',
            'is_active' => true,
        ]);

        $driver = DeliveryDriver::create([
            'name' => 'Repartidor Uno',
            'phone' => '3002223344',
            'vehicle_type' => 'moto',
            'vehicle_plate' => 'ABC123',
            'is_active' => true,
        ]);

        $category = ProductCategory::create([
            'name' => 'Combos',
            'slug' => 'combos',
            'description' => 'Promociones del restaurante',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $burger = $this->createMenuProduct($category, 'Hamburguesa clasica', 'HAMB-001', 15000, 1);
        $soda = $this->createMenuProduct($category, 'Gaseosa personal', 'SODA-001', 5000, 2);

        $createResponse = $this
            ->actingAs($user)
            ->post(route('deliveries.store'), [
                'customer_id' => $customer->id,
                'delivery_driver_id' => $driver->id,
                'customer_name' => 'Cliente Domicilio',
                'customer_phone' => '3001112233',
                'delivery_address' => 'Calle 10 # 20-30',
                'reference' => 'Casa azul',
                'delivery_fee_is_free' => '0',
                'delivery_fee' => 5000,
                'customer_payment_amount' => 50000,
                'scheduled_at' => now()->addHour()->format('Y-m-d H:i:s'),
                'notes' => 'Entregar rapido',
                'items' => [
                    ['product_id' => $burger->id, 'quantity' => 1],
                    ['product_id' => $soda->id, 'quantity' => 2],
                ],
            ]);

        $createResponse->assertRedirect(route('deliveries.index'));

        $delivery = Delivery::query()->firstOrFail();

        $this->assertTrue(Str::startsWith($delivery->delivery_number, 'DOM-'));

        $this->assertDatabaseHas('deliveries', [
            'id' => $delivery->id,
            'status' => 'active',
            'order_total' => '25000.00',
            'delivery_fee' => '5000.00',
            'total_charge' => '30000.00',
            'change_required' => '20000.00',
        ]);

        $this->assertDatabaseHas('delivery_items', [
            'delivery_id' => $delivery->id,
            'product_id' => $burger->id,
            'product_name' => 'Hamburguesa clasica',
            'unit_price' => '15000.00',
            'quantity' => 1,
            'subtotal' => '15000.00',
        ]);

        $this->assertDatabaseHas('delivery_items', [
            'delivery_id' => $delivery->id,
            'product_id' => $soda->id,
            'product_name' => 'Gaseosa personal',
            'unit_price' => '5000.00',
            'quantity' => 2,
            'subtotal' => '10000.00',
        ]);

        $completeResponse = $this
            ->actingAs($user)
            ->put(route('deliveries.complete', $delivery), [
                'delivery_proof_image' => UploadedFile::fake()->image('delivery-proof.jpg'),
            ]);

        $completeResponse->assertRedirect(route('deliveries.index'));

        $delivery->refresh();

        $this->assertSame('delivered', $delivery->status);
        $this->assertNotNull($delivery->delivered_at);
        $this->assertNotNull($delivery->delivery_proof_image_path);
        Storage::disk('public')->assertExists($delivery->delivery_proof_image_path);
        $this->actingAs($user)->get($delivery->delivery_proof_image_url)->assertOk();

        $listResponse = $this
            ->actingAs($user)
            ->get(route('deliveries.index', ['search' => $delivery->delivery_number]));

        $listResponse->assertOk();
        $listResponse->assertSee($delivery->delivery_number);
        $listResponse->assertSee('Cliente Domicilio');
        $listResponse->assertSee('Repartidor Uno');
    }

    public function test_free_delivery_ignores_fee_amount_in_total_charge(): void
    {
        $user = User::factory()->create();
        $adminRole = Role::create([
            'name' => 'Admin',
            'description' => 'Administrador',
        ]);
        $user->roles()->attach($adminRole);

        $category = ProductCategory::create([
            'name' => 'Bebidas',
            'slug' => 'bebidas',
            'description' => 'Bebidas frias',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $product = $this->createMenuProduct($category, 'Limonada natural', 'BEB-001', 12000, 1);

        $response = $this
            ->actingAs($user)
            ->post(route('deliveries.store'), [
                'customer_name' => 'Cliente Gratis',
                'customer_phone' => '3009998877',
                'delivery_address' => 'Calle 99 # 11-22',
                'delivery_fee_is_free' => '1',
                'delivery_fee' => 8000,
                'customer_payment_amount' => 12000,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ]);

        $response->assertRedirect(route('deliveries.index'));

        $delivery = Delivery::query()->latest('id')->firstOrFail();

        $this->assertTrue(Str::startsWith($delivery->delivery_number, 'DOM-'));

        $this->assertDatabaseHas('deliveries', [
            'id' => $delivery->id,
            'status' => 'active',
            'delivery_fee' => '0.00',
            'order_total' => '12000.00',
            'total_charge' => '12000.00',
            'change_required' => '0.00',
        ]);
    }

    public function test_customer_payment_amount_must_cover_total_charge(): void
    {
        $user = User::factory()->create();
        $adminRole = Role::create([
            'name' => 'Admin',
            'description' => 'Administrador',
        ]);
        $user->roles()->attach($adminRole);

        $category = ProductCategory::create([
            'name' => 'Platos',
            'slug' => 'platos',
            'description' => 'Platos fuertes',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $product = $this->createMenuProduct($category, 'Bowl del dia', 'PLA-001', 20000, 1);

        $response = $this
            ->actingAs($user)
            ->from(route('deliveries.create'))
            ->post(route('deliveries.store'), [
                'customer_name' => 'Cliente Cambio',
                'customer_phone' => '3005556677',
                'delivery_address' => 'Carrera 5 # 10-15',
                'delivery_fee_is_free' => '0',
                'delivery_fee' => 5000,
                'customer_payment_amount' => 30000,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ]);

        $response->assertRedirect(route('deliveries.create'));
        $response->assertSessionHasErrors('customer_payment_amount');
    }

    public function test_admin_can_charge_delivery_and_register_cash_movement(): void
    {
        $user = $this->createAdminUser();

        $paymentMethod = PaymentMethod::create([
            'name' => 'Efectivo',
            'code' => 'CASH',
            'description' => 'Pago en efectivo',
            'active' => true,
        ]);

        Box::create([
            'name' => 'Caja domicilios',
            'code' => 'BOX-DELIVERY',
            'user_id' => $user->id,
            'opening_balance' => 200,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $category = ProductCategory::create([
            'name' => 'Especiales',
            'slug' => 'especiales',
            'description' => 'Carta de domicilios',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $product = $this->createMenuProduct($category, 'Perro especial', 'DOM-001', 18000, 1);

        $this->actingAs($user)
            ->post(route('deliveries.store'), [
                'customer_name' => 'Cliente Caja',
                'customer_phone' => '3001110000',
                'delivery_address' => 'Calle 8 # 10-20',
                'reference' => 'Porton negro',
                'delivery_fee_is_free' => '0',
                'delivery_fee' => 4000,
                'customer_payment_amount' => 25000,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ])
            ->assertRedirect(route('deliveries.index'));

        $delivery = Delivery::query()->latest('id')->firstOrFail();

        $this->actingAs($user)
            ->get(route('deliveries.checkout', $delivery))
            ->assertOk()
            ->assertSee($delivery->delivery_number);

        $checkoutResponse = $this->actingAs($user)
            ->post(route('deliveries.checkout.store', $delivery), [
                'payment_method_id' => $paymentMethod->id,
                'amount_received' => 30000,
                'reference' => 'Billete de 30 mil',
            ]);

        $checkoutResponse->assertOk();
        $checkoutResponse->assertSee('Preparando documento');

        $delivery->refresh();

        $this->assertNotNull($delivery->sale_id);

        $this->assertDatabaseHas('deliveries', [
            'id' => $delivery->id,
            'sale_id' => $delivery->sale_id,
            'customer_payment_amount' => '30000.00',
            'change_required' => '8000.00',
        ]);

        $this->assertDatabaseHas('sales', [
            'id' => $delivery->sale_id,
            'customer_name' => 'Cliente Caja',
            'subtotal' => '22000.00',
            'tax_amount' => '0.00',
            'total' => '22000.00',
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $delivery->sale_id,
            'product_id' => $product->id,
            'product_name' => 'Perro especial',
            'quantity' => 1,
            'unit_price' => '18000.00',
            'subtotal' => '18000.00',
        ]);

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $delivery->sale_id,
            'product_id' => null,
            'product_name' => 'Costo domicilio',
            'quantity' => 1,
            'unit_price' => '4000.00',
            'subtotal' => '4000.00',
        ]);

        $this->assertDatabaseHas('payments', [
            'sale_id' => $delivery->sale_id,
            'payment_method_id' => $paymentMethod->id,
            'amount' => '22000.00',
            'received_amount' => '30000.00',
            'change_amount' => '8000.00',
            'reference' => 'Billete de 30 mil',
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('box_movements', [
            'sale_id' => $delivery->sale_id,
            'movement_type' => 'delivery_payment',
            'amount' => '22000.00',
        ]);

        $this->assertDatabaseHas('invoices', [
            'sale_id' => $delivery->sale_id,
            'invoice_type' => 'ticket',
            'status' => 'issued',
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

    private function createMenuProduct(ProductCategory $category, string $name, string $sku, float $price, int $sortOrder): Product
    {
        return Product::create([
            'name' => $name,
            'description' => $name . ' del menu',
            'price' => $price,
            'stock' => 50,
            'tracks_stock' => false,
            'category' => $category->name,
            'category_id' => $category->id,
            'tax_rate_id' => null,
            'product_type' => 'simple',
            'sort_order' => $sortOrder,
            'sku' => $sku,
            'active' => true,
        ]);
    }
}
