<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_reports_dashboard(): void
    {
        [$user] = $this->createAdminUser();
        $this->seedReportData($user);

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Resumen operativo del negocio')
            ->assertSee('Cliente Reportes');
    }

    public function test_user_without_reports_permission_cannot_access_reports(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertForbidden();
    }

    public function test_user_with_export_permission_can_download_csv_report(): void
    {
        $user = User::factory()->create();
        $role = Role::create([
            'name' => 'Cajero',
            'description' => 'Caja',
        ]);
        $viewPermission = Permission::create([
            'name' => 'reports.view',
            'description' => 'Ver reportes',
        ]);
        $exportPermission = Permission::create([
            'name' => 'reports.export',
            'description' => 'Exportar reportes',
        ]);
        $role->permissions()->attach([$viewPermission->id, $exportPermission->id]);
        $user->roles()->attach($role);

        $this->seedReportData($user);

        $response = $this->actingAs($user)
            ->get(route('reports.export'));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertDownload();

        $this->assertStringContainsString(
            'Fecha,Venta,Vendedor,Cliente',
            $response->streamedContent()
        );
    }

    private function createAdminUser(): array
    {
        $user = User::factory()->create();
        $role = Role::create([
            'name' => 'Admin',
            'description' => 'Administrador',
        ]);
        $user->roles()->attach($role);

        return [$user, $role];
    }

    private function seedReportData(User $user): void
    {
        $box = Box::create([
            'name' => 'Caja reportes',
            'code' => 'BOX-REPORT',
            'user_id' => $user->id,
            'opening_balance' => 100,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $customer = Customer::create([
            'name' => 'Cliente Reportes',
            'document_number' => '900000001',
            'email' => 'reportes@example.com',
            'phone' => '3001234567',
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'Hamburguesa Reporte',
            'description' => 'Producto de prueba',
            'price' => 25000,
            'stock' => 10,
            'category' => 'Menu',
            'sku' => 'SKU-REPORT-1',
            'active' => true,
        ]);

        $paymentMethod = PaymentMethod::create([
            'name' => 'Efectivo',
            'code' => 'CASH',
            'active' => true,
        ]);

        $sale = Sale::create([
            'user_id' => $user->id,
            'box_id' => $box->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'subtotal' => 25000,
            'discount_amount' => 0,
            'tax_amount' => 4000,
            'total' => 29000,
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 25000,
            'subtotal' => 25000,
        ]);

        Payment::create([
            'sale_id' => $sale->id,
            'payment_method_id' => $paymentMethod->id,
            'amount' => 29000,
            'received_amount' => 30000,
            'change_amount' => 1000,
            'tip_amount' => 0,
            'status' => 'completed',
        ]);

        Invoice::create([
            'sale_id' => $sale->id,
            'invoice_number' => 'INV-TEST-0001',
            'invoice_type' => 'sale',
            'status' => 'validated',
            'issued_at' => now(),
        ]);

        Delivery::create([
            'customer_id' => $customer->id,
            'assigned_user_id' => $user->id,
            'delivery_number' => 'DOM-20260424-0001',
            'customer_name' => $customer->name,
            'customer_phone' => '3001234567',
            'delivery_address' => 'Calle 1 # 2-3',
            'order_total' => 25000,
            'delivery_fee' => 4000,
            'total_charge' => 29000,
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }
}
