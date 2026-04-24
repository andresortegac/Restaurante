<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Customer;
use App\Models\ElectronicInvoiceSetting;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Services\Factus\ElectronicInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ElectronicInvoiceFactusTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_sends_invoice_to_factus_and_stores_artifacts(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $role = Role::create([
            'name' => 'Admin',
            'description' => 'Administrador',
        ]);
        $user->roles()->attach($role);

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

        $box = Box::create([
            'name' => 'Caja 1',
            'code' => 'BOX-001',
            'status' => 'open',
            'opening_balance' => 100,
            'opened_at' => now(),
        ]);

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
                'message' => 'Documento registrado y validado con éxito',
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
}
