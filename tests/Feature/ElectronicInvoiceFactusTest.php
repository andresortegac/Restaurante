<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Services\Factus\ElectronicInvoiceService;
use App\Services\Factus\FactusApiClient;
use App\Services\Factus\FactusApiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ElectronicInvoiceFactusTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_sends_invoice_to_factus_and_stores_artifacts(): void
    {
        Cache::flush();
        Storage::fake('local');
        $this->configureFactus();

        $user = $this->adminUser();

        $customer = Customer::create([
            'name' => 'Cliente Factus',
            'document_number' => '123456789',
            'billing_identification' => '123456789',
            'identification_document_code' => '13',
            'legal_organization_code' => '2',
            'tribute_code' => 'ZZ',
            'municipality_code' => '68001',
            'phone' => '3001234567',
            'billing_address' => 'Calle 1 # 2-3',
            'trade_name' => 'Cliente Factus',
            'email' => 'cliente@example.com',
            'is_active' => true,
        ]);

        $box = $this->openBox('BOX-001', 100);

        $sale = Sale::create([
            'user_id' => $user->id,
            'box_id' => $box->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'status' => 'completed',
        ]);

        $product = Product::create([
            'name' => 'Producto FE',
            'description' => 'Prueba',
            'price' => 10000,
            'stock' => 10,
            'tracks_stock' => false,
            'category' => 'Platos',
            'sku' => 'FE-001',
            'product_type' => 'simple',
            'active' => true,
        ]);

        $sale->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 10000,
            'subtotal' => 10000,
        ]);
        $sale->calculateTotal();

        $paymentMethod = PaymentMethod::create([
            'name' => 'Efectivo',
            'code' => 'CASH',
            'description' => 'Pago efectivo',
            'active' => true,
        ]);

        $sale->payments()->create([
            'payment_method_id' => $paymentMethod->id,
            'amount' => $sale->total,
            'received_amount' => $sale->total,
            'change_amount' => 0,
            'tip_amount' => 0,
            'status' => 'completed',
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

        $invoice = app(ElectronicInvoiceService::class)->issueForSale($sale)->fresh();

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'sale_id' => $sale->id,
            'provider' => 'factus',
            'status' => 'validated',
            'electronic_number' => 'SETP990001103',
            'cufe' => 'CUFE-123',
        ]);

        $this->assertDatabaseHas('electronic_invoice_logs', [
            'invoice_id' => $invoice->id,
            'event' => 'invoice.sent',
        ]);

        Storage::disk('local')->assertExists($invoice->pdf_path);
        Storage::disk('local')->assertExists($invoice->xml_path);
    }

    public function test_index_filters_electronic_invoices_by_customer_and_shows_download_actions(): void
    {
        $admin = $this->adminUser();

        $matchingCustomer = Customer::create([
            'name' => 'Cliente Alicia',
            'document_number' => '900111222',
            'email' => 'alicia@example.com',
            'is_active' => true,
        ]);

        $otherCustomer = Customer::create([
            'name' => 'Cliente Bruno',
            'document_number' => '900333444',
            'email' => 'bruno@example.com',
            'is_active' => true,
        ]);
        $box = $this->openBox('BOX-FILTER');

        $matchingSale = Sale::create([
            'user_id' => $admin->id,
            'box_id' => $box->id,
            'customer_id' => $matchingCustomer->id,
            'customer_name' => $matchingCustomer->name,
            'status' => 'completed',
            'total' => 25000,
        ]);

        $otherSale = Sale::create([
            'user_id' => $admin->id,
            'box_id' => $box->id,
            'customer_id' => $otherCustomer->id,
            'customer_name' => $otherCustomer->name,
            'status' => 'completed',
            'total' => 18000,
        ]);

        $matchingInvoice = Invoice::create([
            'sale_id' => $matchingSale->id,
            'invoice_number' => 'INV-ALICIA',
            'invoice_type' => Invoice::TYPE_ELECTRONIC,
            'provider' => 'factus',
            'reference_code' => 'REF-ALICIA',
            'electronic_number' => 'SETP990001',
            'cufe' => 'CUFE-ALICIA',
            'status' => 'validated',
            'issued_at' => now(),
        ]);

        Invoice::create([
            'sale_id' => $otherSale->id,
            'invoice_number' => 'INV-BRUNO',
            'invoice_type' => Invoice::TYPE_ELECTRONIC,
            'provider' => 'factus',
            'status' => 'validated',
            'issued_at' => now(),
        ]);

        Invoice::create([
            'sale_id' => $matchingSale->id,
            'invoice_number' => 'TKT-LOCAL',
            'invoice_type' => Invoice::TYPE_TICKET,
            'provider' => 'local',
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('electronic-invoices.index', ['search' => 'Alicia']));

        $response->assertOk();
        $response->assertSee('INV-ALICIA');
        $response->assertSee('Cliente Alicia');
        $response->assertSee(route('electronic-invoices.pdf', $matchingInvoice), false);
        $response->assertSee(route('electronic-invoices.xml', $matchingInvoice), false);
        $response->assertDontSee('INV-BRUNO');
        $response->assertDontSee('TKT-LOCAL');
    }

    public function test_pdf_download_fetches_artifacts_from_factus_when_missing_locally(): void
    {
        Cache::flush();
        Storage::fake('local');
        $this->configureFactus();

        $admin = $this->adminUser();
        $box = $this->openBox('BOX-DOWNLOAD');
        $sale = Sale::create([
            'user_id' => $admin->id,
            'box_id' => $box->id,
            'customer_name' => 'Cliente Descarga',
            'status' => 'completed',
            'total' => 12000,
        ]);

        $invoice = Invoice::create([
            'sale_id' => $sale->id,
            'invoice_number' => 'INV-DOWNLOAD',
            'invoice_type' => Invoice::TYPE_ELECTRONIC,
            'provider' => 'factus',
            'electronic_number' => 'SETP990002',
            'status' => 'validated',
            'issued_at' => now(),
        ]);

        Http::fake([
            'https://api-sandbox.factus.com.co/oauth/token' => Http::response([
                'token_type' => 'Bearer',
                'expires_in' => 600,
                'access_token' => 'token-123',
            ], 200),
            'https://api-sandbox.factus.com.co/v2/bills/SETP990002/download-pdf' => Http::response([
                'data' => [
                    'file_name' => 'factura-descarga',
                    'pdf_base_64_encoded' => base64_encode('pdf-content'),
                ],
            ], 200),
            'https://api-sandbox.factus.com.co/v2/bills/SETP990002/download-xml/' => Http::response([
                'data' => [
                    'file_name' => 'factura-descarga',
                    'xml_base_64_encoded' => base64_encode('<xml>demo</xml>'),
                ],
            ], 200),
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('electronic-invoices.pdf', $invoice));

        $response->assertOk();

        $invoice->refresh();
        Storage::disk('local')->assertExists($invoice->pdf_path);
        Storage::disk('local')->assertExists($invoice->xml_path);
    }

    public function test_service_uses_internal_factus_configuration(): void
    {
        $this->configureFactus([
            'factus.client_id' => 'configured-client',
            'factus.username' => 'configured@example.com',
            'factus.numbering_range_id' => 7,
            'factus.default_municipality_code' => '68001',
        ]);

        $settings = app(ElectronicInvoiceService::class)->settings();

        $this->assertTrue($settings->is_enabled);
        $this->assertSame('configured-client', $settings->client_id);
        $this->assertSame('configured@example.com', $settings->username);
        $this->assertSame(7, $settings->numbering_range_id);
        $this->assertFalse($settings->send_email);
        $this->assertSame('68001', $settings->default_municipality_code);
    }

    public function test_factus_api_version_403_returns_clear_message(): void
    {
        Cache::flush();
        $this->configureFactus();

        Http::fake([
            'https://api-sandbox.factus.com.co/oauth/token' => Http::response([
                'token_type' => 'Bearer',
                'expires_in' => 600,
                'access_token' => 'token-123',
            ], 200),
            'https://api-sandbox.factus.com.co/v2/bills/validate' => Http::response([
                'message' => 'Version de API no disponible para esta empresa',
            ], 403),
        ]);

        $this->expectException(FactusApiException::class);
        $this->expectExceptionMessage('Factus autentico las credenciales, pero esta empresa no tiene habilitada la API v2.');

        app(FactusApiClient::class)->createBill(
            app(ElectronicInvoiceService::class)->settings(),
            ['reference_code' => 'TEST-403']
        );
    }

    private function configureFactus(array $overrides = []): void
    {
        config(array_merge([
            'factus.enabled' => true,
            'factus.environment' => 'sandbox',
            'factus.client_id' => 'client-id',
            'factus.client_secret' => 'client-secret',
            'factus.username' => 'api@example.com',
            'factus.password' => 'secret-password',
            'factus.numbering_range_id' => 4,
            'factus.document_code' => '01',
            'factus.operation_type' => '10',
            'factus.send_email' => false,
            'factus.default_identification_document_code' => '13',
            'factus.default_legal_organization_code' => '2',
            'factus.default_tribute_code' => 'ZZ',
            'factus.default_municipality_code' => '68001',
            'factus.default_unit_measure_code' => '94',
            'factus.default_standard_code' => '999',
        ], $overrides));
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate([
            'name' => 'Admin',
        ], [
            'description' => 'Administrador',
        ]);
        $user->roles()->attach($role);

        return $user;
    }

    private function openBox(string $code, int $openingBalance = 0): Box
    {
        return Box::create([
            'name' => $code,
            'code' => $code,
            'status' => 'open',
            'opening_balance' => $openingBalance,
            'opened_at' => now(),
        ]);
    }
}
