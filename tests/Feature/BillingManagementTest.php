<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Customer;
use App\Models\CustomerCredit;
use App\Models\ElectronicInvoiceSetting;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Sale;
use App\Models\TableOrder;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BillingManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_index_lists_open_orders_pending_collection(): void
    {
        $user = $this->createAdminUser();

        $table = RestaurantTable::create([
            'name' => 'Mesa 4',
            'code' => 'M-04',
            'area' => 'Salon',
            'capacity' => 4,
            'status' => 'occupied',
            'is_active' => true,
        ]);

        $order = TableOrder::create([
            'restaurant_table_id' => $table->id,
            'order_number' => 'PED-BILL-001',
            'customer_name' => 'Laura Mesa',
            'status' => 'open',
            'opened_by_user_id' => $user->id,
        ]);

        $order->items()->create([
            'product_name' => 'Menu del dia',
            'unit_price' => 20000,
            'quantity' => 1,
            'subtotal' => 20000,
            'split_group' => 1,
        ]);
        $order->recalculateTotals();

        $response = $this
            ->actingAs($user)
            ->get(route('billing.index'));

        $response->assertOk();
        $response->assertViewIs('billing.index');
        $response->assertSee('Cuentas por cobrar');
        $response->assertSee('PED-BILL-001');
        $response->assertSee('Mesa 4');
        $response->assertSee('Laura Mesa');
    }

    public function test_billing_checkout_can_generate_electronic_invoice_and_return_cufe(): void
    {
        Storage::fake('local');

        $user = $this->createAdminUser();

        $paymentMethod = PaymentMethod::create([
            'name' => 'Efectivo',
            'code' => 'CASH',
            'description' => 'Pago en efectivo',
            'active' => true,
        ]);

        Box::create([
            'name' => 'Caja principal',
            'code' => 'BOX-FE',
            'user_id' => $user->id,
            'opening_balance' => 100,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $customer = Customer::create([
            'name' => 'Cliente FE',
            'document_number' => '123456789',
            'billing_identification' => '123456789',
            'identification_document_code' => '13',
            'legal_organization_code' => '2',
            'tribute_code' => 'ZZ',
            'municipality_code' => '68001',
            'phone' => '3001234567',
            'billing_address' => 'Calle 1 # 2-3',
            'trade_name' => 'Cliente FE',
            'email' => 'cliente@example.com',
            'is_active' => true,
        ]);

        $table = RestaurantTable::create([
            'name' => 'Mesa 8',
            'code' => 'M-08',
            'area' => 'Terraza',
            'capacity' => 4,
            'status' => 'occupied',
            'is_active' => true,
        ]);

        $taxRate = TaxRate::create([
            'name' => 'IVA General',
            'code' => 'IVA-16',
            'rate' => 16,
            'is_inclusive' => false,
            'is_default' => true,
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'Plato FE',
            'description' => 'Producto de prueba',
            'price' => 10000,
            'stock' => 20,
            'tracks_stock' => false,
            'category' => 'Platos',
            'sku' => 'BILL-FE-001',
            'tax_rate_id' => $taxRate->id,
            'product_type' => 'simple',
            'active' => true,
        ]);

        $order = TableOrder::create([
            'restaurant_table_id' => $table->id,
            'order_number' => 'PED-BILL-FE-001',
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'status' => 'open',
            'opened_by_user_id' => $user->id,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => 10000,
            'quantity' => 1,
            'subtotal' => 10000,
            'split_group' => 1,
        ]);
        $order->recalculateTotals();

        ElectronicInvoiceSetting::create([
            'is_enabled' => true,
            'environment' => 'sandbox',
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'username' => 'api@example.com',
            'password' => 'secret-password',
            'numbering_range_id' => 4,
            'document_code' => '01',
            'operation_type' => '10',
            'send_email' => false,
            'default_identification_document_code' => '13',
            'default_legal_organization_code' => '2',
            'default_tribute_code' => 'ZZ',
            'default_municipality_code' => '68001',
            'default_unit_measure_code' => '94',
            'default_standard_code' => '999',
        ]);

        Http::fake([
            'https://api-sandbox.factus.com.co/oauth/token' => Http::response([
                'token_type' => 'Bearer',
                'expires_in' => 600,
                'access_token' => 'token-123',
                'refresh_token' => 'refresh-123',
            ], 200),
            'https://api-sandbox.factus.com.co/v2/bills/validate' => Http::response([
                'status' => 'Created',
                'message' => 'Documento registrado y validado con exito',
                'data' => [
                    'reference_code' => 'SALE-REF',
                    'number' => 'SETP990001103',
                    'is_validated' => true,
                    'validated_at' => '02-03-2026 11:12:48 AM',
                    'errors' => null,
                    'cufe' => 'CUFE-123',
                    'public_url' => 'https://factus.test/public/123',
                    'qr' => 'https://factus.test/qr/123',
                ],
            ], 201),
            'https://api-sandbox.factus.com.co/v2/bills/SETP990001103/download-pdf' => Http::response([
                'status' => 'OK',
                'message' => 'Solicitud exitosa',
                'data' => [
                    'file_name' => 'factura-demo',
                    'pdf_base_64_encoded' => base64_encode('pdf-content'),
                ],
            ], 200),
            'https://api-sandbox.factus.com.co/v2/bills/SETP990001103/download-xml/' => Http::response([
                'status' => 'OK',
                'message' => 'Solicitud exitosa',
                'data' => [
                    'file_name' => 'factura-demo',
                    'xml_base_64_encoded' => base64_encode('<xml>demo</xml>'),
                ],
            ], 200),
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(route('billing.checkout.store', $order), [
                'payment_method_id' => $paymentMethod->id,
                'amount_received' => 11600,
                'tip_amount' => 0,
                'document_type' => 'electronic',
            ]);

        $response->assertOk();
        $response->assertJsonPath('cufe', 'CUFE-123');

        $sale = Sale::query()->firstOrFail();

        $this->assertDatabaseHas('invoices', [
            'sale_id' => $sale->id,
            'invoice_type' => 'electronic',
            'status' => 'validated',
            'cufe' => 'CUFE-123',
        ]);

        $this->assertDatabaseHas('table_orders', [
            'id' => $order->id,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('restaurant_tables', [
            'id' => $table->id,
            'status' => 'free',
        ]);
    }

    public function test_billing_history_lists_paid_table_sales_with_document_data(): void
    {
        $user = $this->createAdminUser();

        $box = Box::create([
            'name' => 'Caja historial',
            'code' => 'BOX-HISTORY',
            'user_id' => $user->id,
            'opening_balance' => 100,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $table = RestaurantTable::create([
            'name' => 'Mesa 10',
            'code' => 'M-10',
            'area' => 'Salon',
            'capacity' => 4,
            'status' => 'free',
            'is_active' => true,
        ]);

        $order = TableOrder::create([
            'restaurant_table_id' => $table->id,
            'order_number' => 'PED-HISTORY-001',
            'customer_name' => 'Pedro Historia',
            'status' => 'paid',
            'opened_by_user_id' => $user->id,
        ]);

        $sale = Sale::create([
            'user_id' => $user->id,
            'box_id' => $box->id,
            'table_order_id' => $order->id,
            'customer_name' => 'Pedro Historia',
            'status' => 'completed',
            'subtotal' => 10000,
            'tax_amount' => 1600,
            'total' => 11600,
        ]);

        $sale->invoice()->create([
            'invoice_number' => 'TKT-202605-000001',
            'invoice_type' => 'ticket',
            'provider' => 'local',
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('billing.history'));

        $response->assertOk();
        $response->assertViewIs('billing.history');
        $response->assertSee('Ventas generales');
        $response->assertSee('PED-HISTORY-001');
        $response->assertSee('Mesa 10');
        $response->assertSee('TKT-202605-000001');
    }

    public function test_manual_billing_registers_table_charge_by_default(): void
    {
        $user = $this->createAdminUser();

        $paymentMethod = PaymentMethod::create([
            'name' => 'Efectivo',
            'code' => 'CASH',
            'description' => 'Pago en efectivo',
            'active' => true,
        ]);

        $box = Box::create([
            'name' => 'Caja manual',
            'code' => 'BOX-MANUAL',
            'user_id' => $user->id,
            'opening_balance' => 50000,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(route('billing.manual.store'), [
                'origin_type' => 'table',
                'origin_reference' => 'Mesa antigua 2',
                'customer_name' => 'Cliente manual',
                'document_type' => 'ticket',
                'payment_method_id' => $paymentMethod->id,
                'amount_received' => 25000,
                'items' => [
                    [
                        'name' => 'Cuenta heredada',
                        'quantity' => 1,
                        'unit_price' => 25000,
                    ],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Cobro manual registrado correctamente.');

        $this->assertDatabaseHas('sales', [
            'box_id' => $box->id,
            'customer_name' => 'Cliente manual',
            'status' => 'completed',
            'total' => 25000,
            'notes' => 'Pedido de mesa manual | Referencia: Mesa antigua 2',
        ]);

        $sale = Sale::query()->firstOrFail();

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'product_name' => 'Cuenta heredada',
            'quantity' => 1,
            'unit_price' => 25000,
            'subtotal' => 25000,
        ]);

        $this->assertDatabaseHas('payments', [
            'sale_id' => $sale->id,
            'payment_method_id' => $paymentMethod->id,
            'amount' => 25000,
            'received_amount' => 25000,
            'change_amount' => 0,
        ]);

        $this->assertDatabaseHas('invoices', [
            'sale_id' => $sale->id,
            'invoice_type' => 'ticket',
            'status' => 'issued',
        ]);

        $this->assertDatabaseHas('box_movements', [
            'sale_id' => $sale->id,
            'movement_type' => 'manual_payment',
            'amount' => 25000,
        ]);
    }

    public function test_manual_billing_can_be_registered_without_payment_method(): void
    {
        $user = $this->createAdminUser();

        $box = Box::create([
            'name' => 'Caja manual sin metodo',
            'code' => 'BOX-MANUAL-NO-METHOD',
            'user_id' => $user->id,
            'opening_balance' => 50000,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(route('billing.manual.store'), [
                'origin_type' => 'table',
                'origin_reference' => 'Venta sin metodo',
                'customer_name' => 'Cliente manual',
                'document_type' => 'ticket',
                'payment_method_id' => null,
                'amount_received' => 18000,
                'items' => [
                    [
                        'name' => 'Cuenta sin metodo',
                        'quantity' => 1,
                        'unit_price' => 18000,
                    ],
                ],
            ]);

        $response->assertOk();

        $sale = Sale::query()->firstOrFail();

        $this->assertDatabaseHas('payments', [
            'sale_id' => $sale->id,
            'payment_method_id' => null,
            'amount' => 18000,
            'received_amount' => 18000,
            'change_amount' => 0,
        ]);

        $this->assertDatabaseHas('box_movements', [
            'sale_id' => $sale->id,
            'movement_type' => 'manual_payment',
            'amount' => 18000,
            'balance_before' => 50000,
            'balance_after' => 68000,
        ]);
    }

    public function test_manual_billing_can_register_and_pay_customer_credit(): void
    {
        $user = $this->createAdminUser();

        $box = Box::create([
            'name' => 'Caja creditos',
            'code' => 'BOX-CREDIT',
            'user_id' => $user->id,
            'opening_balance' => 10000,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $customer = Customer::create([
            'name' => 'Cliente Credito',
            'document_number' => '900123',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(route('billing.manual.store'), [
                'origin_type' => 'table',
                'customer_id' => $customer->id,
                'document_type' => 'ticket',
                'is_credit' => true,
                'credit_due_at' => today()->addDays(8)->toDateString(),
                'amount_received' => 0,
                'items' => [
                    [
                        'name' => 'Cuenta a credito',
                        'quantity' => 1,
                        'unit_price' => 32000,
                    ],
                ],
            ]);

        $response->assertOk();

        $sale = Sale::query()->firstOrFail();

        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'customer_id' => $customer->id,
            'status' => 'credit',
            'payment_status' => 'credit',
            'total' => 32000,
        ]);

        $this->assertDatabaseHas('payments', [
            'sale_id' => $sale->id,
            'payment_method_id' => null,
            'received_amount' => 0,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('box_movements', [
            'sale_id' => $sale->id,
            'movement_type' => 'manual_payment',
            'amount' => 0,
            'balance_before' => 10000,
            'balance_after' => 10000,
        ]);

        $payResponse = $this
            ->actingAs($user)
            ->post(route('billing.credits.pay', $sale), [
                'amount_received' => 12000,
            ]);

        $payResponse
            ->assertRedirect(route('billing.history', ['payment_status' => 'credit']));

        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'status' => 'credit',
            'payment_status' => 'credit',
        ]);

        $this->assertDatabaseHas('payments', [
            'sale_id' => $sale->id,
            'received_amount' => 12000,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('customer_credits', [
            'sale_id' => $sale->id,
            'balance' => 20000,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('box_movements', [
            'sale_id' => $sale->id,
            'movement_type' => 'credit_payment',
            'amount' => 12000,
            'balance_before' => 10000,
            'balance_after' => 22000,
        ]);

        $finalPayResponse = $this
            ->actingAs($user)
            ->post(route('billing.credits.pay', $sale), [
                'amount_received' => 20000,
            ]);

        $finalPayResponse
            ->assertRedirect(route('billing.history', ['payment_status' => 'credit']));

        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'credit_due_at' => null,
        ]);

        $this->assertDatabaseHas('payments', [
            'sale_id' => $sale->id,
            'received_amount' => 32000,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('customer_credits', [
            'sale_id' => $sale->id,
            'balance' => 0,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('box_movements', [
            'sale_id' => $sale->id,
            'movement_type' => 'credit_payment',
            'amount' => 20000,
            'balance_before' => 22000,
            'balance_after' => 42000,
        ]);
    }

    public function test_billing_checkout_can_send_table_order_to_customer_credit_without_affecting_cash(): void
    {
        $user = $this->createAdminUser();

        Box::create([
            'name' => 'Caja cartera mesas',
            'code' => 'BOX-CREDIT-TABLE',
            'user_id' => $user->id,
            'opening_balance' => 25000,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $customer = Customer::create([
            'name' => 'Cliente Cartera Mesa',
            'document_number' => '100200300',
            'is_active' => true,
        ]);

        $table = RestaurantTable::create([
            'name' => 'Mesa credito 4',
            'code' => 'M-24',
            'area' => 'Salon',
            'capacity' => 4,
            'status' => 'occupied',
            'is_active' => true,
        ]);

        $order = TableOrder::create([
            'restaurant_table_id' => $table->id,
            'order_number' => 'PED-CREDIT-TABLE-001',
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'status' => 'open',
            'opened_by_user_id' => $user->id,
        ]);

        $order->items()->create([
            'product_name' => 'Cuenta enviada a credito',
            'unit_price' => 28000,
            'quantity' => 1,
            'subtotal' => 28000,
            'split_group' => 1,
        ]);
        $order->recalculateTotals();

        $response = $this
            ->actingAs($user)
            ->postJson(route('billing.checkout.store', $order), [
                'is_credit' => true,
                'amount_received' => 0,
                'document_type' => 'ticket',
            ]);

        $response->assertOk();

        $sale = Sale::query()->firstOrFail();

        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'customer_id' => $customer->id,
            'table_order_id' => $order->id,
            'status' => 'credit',
            'payment_status' => 'credit',
            'credit_due_at' => null,
        ]);

        $this->assertDatabaseHas('payments', [
            'sale_id' => $sale->id,
            'received_amount' => 0,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('box_movements', [
            'sale_id' => $sale->id,
            'movement_type' => 'table_order_payment',
            'amount' => 0,
            'balance_before' => 25000,
            'balance_after' => 25000,
        ]);

        $this->assertDatabaseHas('customer_credits', [
            'sale_id' => $sale->id,
            'customer_id' => $customer->id,
            'source_type' => 'table_order',
            'balance' => 28000,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('table_orders', [
            'id' => $order->id,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('restaurant_tables', [
            'id' => $table->id,
            'status' => 'free',
        ]);
    }

    public function test_billing_checkout_can_assign_customer_during_credit_collection_when_order_has_no_customer(): void
    {
        $user = $this->createAdminUser();

        Box::create([
            'name' => 'Caja credito sin cliente previo',
            'code' => 'BOX-CREDIT-NO-CUSTOMER',
            'user_id' => $user->id,
            'opening_balance' => 25000,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $customer = Customer::create([
            'name' => 'Cliente vinculado en cobro',
            'document_number' => '99887766',
            'is_active' => true,
        ]);

        $table = RestaurantTable::create([
            'name' => 'Mesa credito 5',
            'code' => 'M-25',
            'area' => 'Salon',
            'capacity' => 4,
            'status' => 'occupied',
            'is_active' => true,
        ]);

        $order = TableOrder::create([
            'restaurant_table_id' => $table->id,
            'order_number' => 'PED-CREDIT-TABLE-002',
            'status' => 'open',
            'opened_by_user_id' => $user->id,
        ]);

        $order->items()->create([
            'product_name' => 'Cuenta sin cliente inicial',
            'unit_price' => 31000,
            'quantity' => 1,
            'subtotal' => 31000,
            'split_group' => 1,
        ]);
        $order->recalculateTotals();

        $response = $this
            ->actingAs($user)
            ->postJson(route('billing.checkout.store', $order), [
                'customer_id' => $customer->id,
                'is_credit' => true,
                'amount_received' => 0,
                'document_type' => 'ticket',
            ]);

        $response->assertOk();

        $sale = Sale::query()->firstOrFail();

        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'table_order_id' => $order->id,
            'status' => 'credit',
            'payment_status' => 'credit',
            'credit_due_at' => null,
        ]);

        $this->assertDatabaseHas('table_orders', [
            'id' => $order->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('customer_credits', [
            'sale_id' => $sale->id,
            'customer_id' => $customer->id,
            'source_type' => 'table_order',
            'balance' => 31000,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('box_movements', [
            'sale_id' => $sale->id,
            'movement_type' => 'table_order_payment',
            'amount' => 0,
            'balance_before' => 25000,
            'balance_after' => 25000,
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
