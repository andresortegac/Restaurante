<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingManagementController;
use App\Http\Controllers\CustomerManagementController;
use App\Http\Controllers\DeliveryDriverManagementController;
use App\Http\Controllers\OrderManagementController;
use App\Http\Controllers\ProductManagementController;
use App\Http\Controllers\ReservationManagementController;
use App\Http\Controllers\TableManagementController;
use App\Http\Controllers\CashManagementController;
use App\Http\Controllers\DeliveryManagementController;
use App\Http\Controllers\ElectronicInvoiceManagementController;
use App\Http\Controllers\ReportManagementController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\RoleManagementController;
use App\Http\Controllers\PermissionManagementController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Rutas publicas
Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Rutas de autenticacion
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// Rutas protegidas
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/media/public', function () {
        $path = (string) request()->query('path', '');

        abort_if($path === '', 404);
        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path);
    })->name('media.public');

    // Dashboard
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::prefix('cash-management')->name('cash-management.')->group(function () {
        Route::get('/', [CashManagementController::class, 'index'])->name('index');
        Route::get('/create', [CashManagementController::class, 'create'])->name('create');
        Route::post('/', [CashManagementController::class, 'store'])->name('store');
        Route::get('/history', [CashManagementController::class, 'history'])->name('history');
        Route::get('/monthly', [CashManagementController::class, 'monthlyReport'])->name('monthly');
        Route::get('/{box}/edit', [CashManagementController::class, 'edit'])->name('edit');
        Route::put('/{box}', [CashManagementController::class, 'update'])->name('update');
        Route::get('/{box}', [CashManagementController::class, 'show'])->name('show');
        Route::post('/{box}/open', [CashManagementController::class, 'open'])->name('open');
        Route::post('/{box}/movements', [CashManagementController::class, 'storeMovement'])->name('movements.store');
        Route::post('/{box}/close', [CashManagementController::class, 'close'])->name('close');
    });

    Route::prefix('electronic-invoices')->name('electronic-invoices.')->group(function () {
        Route::get('/', [ElectronicInvoiceManagementController::class, 'index'])->name('index');
        Route::get('/settings', [ElectronicInvoiceManagementController::class, 'settings'])->name('settings');
        Route::put('/settings', [ElectronicInvoiceManagementController::class, 'updateSettings'])->name('settings.update');
        Route::get('/{invoice}', [ElectronicInvoiceManagementController::class, 'show'])->name('show');
        Route::post('/{invoice}/retry', [ElectronicInvoiceManagementController::class, 'retry'])->name('retry');
        Route::post('/{invoice}/sync', [ElectronicInvoiceManagementController::class, 'sync'])->name('sync');
        Route::get('/{invoice}/download-pdf', [ElectronicInvoiceManagementController::class, 'downloadPdf'])->name('pdf');
        Route::get('/{invoice}/download-xml', [ElectronicInvoiceManagementController::class, 'downloadXml'])->name('xml');
    });

    Route::prefix('billing')->name('billing.')->group(function () {
        Route::get('/', [BillingManagementController::class, 'index'])->name('index');
        Route::get('/history', [BillingManagementController::class, 'history'])->name('history');
        Route::get('/{order}/checkout', [BillingManagementController::class, 'showCheckout'])->name('checkout');
        Route::post('/{order}/checkout', [BillingManagementController::class, 'processCheckout'])->name('checkout.store');
    });

    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportManagementController::class, 'index'])->name('index');
        Route::get('/analytics', [ReportManagementController::class, 'analytics'])->name('analytics');
        Route::get('/export', [ReportManagementController::class, 'export'])->name('export');
    });

    // Administracion de acceso
    Route::middleware('role:Admin')->prefix('admin')->name('admin.')->group(function () {
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [UserManagementController::class, 'index'])->name('index');
            Route::get('/create', [UserManagementController::class, 'create'])->name('create');
            Route::post('/', [UserManagementController::class, 'store'])->name('store');
            Route::get('/{user}/edit', [UserManagementController::class, 'edit'])->name('edit');
            Route::put('/{user}', [UserManagementController::class, 'update'])->name('update');
            Route::delete('/{user}', [UserManagementController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('roles')->name('roles.')->group(function () {
            Route::get('/', [RoleManagementController::class, 'index'])->name('index');
            Route::get('/create', [RoleManagementController::class, 'create'])->name('create');
            Route::post('/', [RoleManagementController::class, 'store'])->name('store');
            Route::get('/{role}/edit', [RoleManagementController::class, 'edit'])->name('edit');
            Route::put('/{role}', [RoleManagementController::class, 'update'])->name('update');
            Route::delete('/{role}', [RoleManagementController::class, 'destroy'])->name('destroy');
        });

        Route::get('/permissions', [PermissionManagementController::class, 'index'])->name('permissions.index');
    });

    // Gestion de productos
    Route::prefix('products')->name('products.')->group(function () {
        Route::get('/menu', [ProductManagementController::class, 'menu'])->name('menu.index');
        Route::get('/menu/create', [ProductManagementController::class, 'createMenuProduct'])->name('menu.create');
        Route::post('/menu', [ProductManagementController::class, 'storeMenuProduct'])->name('menu.store');
        Route::get('/menu/{product}/edit', [ProductManagementController::class, 'editMenuProduct'])->name('menu.edit');
        Route::put('/menu/{product}', [ProductManagementController::class, 'updateMenuProduct'])->name('menu.update');
        Route::delete('/menu/{product}', [ProductManagementController::class, 'destroyMenuProduct'])->name('menu.destroy');
        Route::post('/categories', [ProductManagementController::class, 'storeCategory'])->name('categories.store');
        Route::put('/categories/{category}', [ProductManagementController::class, 'updateCategory'])->name('categories.update');
        Route::delete('/categories/{category}', [ProductManagementController::class, 'destroyCategory'])->name('categories.destroy');

        Route::get('/combos', [ProductManagementController::class, 'combos'])->name('combos.index');
        Route::get('/combos/create', [ProductManagementController::class, 'createCombo'])->name('combos.create');
        Route::post('/combos', [ProductManagementController::class, 'storeCombo'])->name('combos.store');
        Route::get('/combos/{product}/edit', [ProductManagementController::class, 'editCombo'])->name('combos.edit');
        Route::put('/combos/{product}', [ProductManagementController::class, 'updateCombo'])->name('combos.update');
        Route::delete('/combos/{product}', [ProductManagementController::class, 'destroyCombo'])->name('combos.destroy');

        Route::get('/taxes', [ProductManagementController::class, 'taxes'])->name('taxes.index');
        Route::get('/taxes/create', [ProductManagementController::class, 'createTax'])->name('taxes.create');
        Route::post('/taxes', [ProductManagementController::class, 'storeTax'])->name('taxes.store');
        Route::get('/taxes/{taxRate}/edit', [ProductManagementController::class, 'editTax'])->name('taxes.edit');
        Route::put('/taxes/{taxRate}', [ProductManagementController::class, 'updateTax'])->name('taxes.update');
        Route::delete('/taxes/{taxRate}', [ProductManagementController::class, 'destroyTax'])->name('taxes.destroy');
    });

    // Gestion de clientes
    Route::prefix('customers')->name('customers.')->group(function () {
        Route::get('/', [CustomerManagementController::class, 'index'])->name('index');
        Route::get('/create', [CustomerManagementController::class, 'create'])->name('create');
        Route::post('/', [CustomerManagementController::class, 'store'])->name('store');
        Route::get('/{customer}/edit', [CustomerManagementController::class, 'edit'])->name('edit');
        Route::put('/{customer}', [CustomerManagementController::class, 'update'])->name('update');
        Route::delete('/{customer}', [CustomerManagementController::class, 'destroy'])->name('destroy');
        Route::get('/api/search', [CustomerManagementController::class, 'search'])->name('search');
    });

    // Gestion de domicilios
    Route::prefix('deliveries')->name('deliveries.')->group(function () {
        Route::prefix('drivers')->name('drivers.')->group(function () {
            Route::get('/', [DeliveryDriverManagementController::class, 'index'])->name('index');
            Route::get('/create', [DeliveryDriverManagementController::class, 'create'])->name('create');
            Route::post('/', [DeliveryDriverManagementController::class, 'store'])->name('store');
            Route::get('/{driver}/edit', [DeliveryDriverManagementController::class, 'edit'])->name('edit');
            Route::put('/{driver}', [DeliveryDriverManagementController::class, 'update'])->name('update');
            Route::delete('/{driver}', [DeliveryDriverManagementController::class, 'destroy'])->name('destroy');
        });
        Route::get('/', [DeliveryManagementController::class, 'index'])->name('index');
        Route::get('/create', [DeliveryManagementController::class, 'create'])->name('create');
        Route::post('/', [DeliveryManagementController::class, 'store'])->name('store');
        Route::put('/{delivery}/complete', [DeliveryManagementController::class, 'complete'])->name('complete');
        Route::get('/{delivery}/edit', [DeliveryManagementController::class, 'edit'])->name('edit');
        Route::put('/{delivery}', [DeliveryManagementController::class, 'update'])->name('update');
        Route::delete('/{delivery}', [DeliveryManagementController::class, 'destroy'])->name('destroy');
    });

    // Gestion de reservas
    Route::prefix('reservations')->name('reservations.')->group(function () {
        Route::get('/', [ReservationManagementController::class, 'index'])->name('index');
        Route::get('/create', [ReservationManagementController::class, 'create'])->name('create');
        Route::post('/', [ReservationManagementController::class, 'store'])->name('store');
        Route::get('/{reservation}/edit', [ReservationManagementController::class, 'edit'])->name('edit');
        Route::put('/{reservation}', [ReservationManagementController::class, 'update'])->name('update');
        Route::delete('/{reservation}', [ReservationManagementController::class, 'destroy'])->name('destroy');
    });

    // Gestion de mesas
    Route::prefix('tables')->name('tables.')->group(function () {
        Route::get('/', [TableManagementController::class, 'index'])->name('index');
        Route::get('/create', [TableManagementController::class, 'create'])->name('create');
        Route::post('/', [TableManagementController::class, 'store'])->name('store');
        Route::get('/{table}', [TableManagementController::class, 'show'])->name('show');
        Route::get('/{table}/edit', [TableManagementController::class, 'edit'])->name('edit');
        Route::put('/{table}', [TableManagementController::class, 'update'])->name('update');
        Route::delete('/{table}', [TableManagementController::class, 'destroy'])->name('destroy');
    });

    // Gestion de pedidos por mesa
    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/', [OrderManagementController::class, 'index'])->name('index');
        Route::get('/history', [OrderManagementController::class, 'history'])->name('history.index');
        Route::get('/tables/{table}', [OrderManagementController::class, 'show'])->name('show');
        Route::post('/tables/{table}', [OrderManagementController::class, 'storeOrder'])->name('store');
        Route::get('/{order}/checkout', [OrderManagementController::class, 'showCheckout'])->name('checkout');
        Route::post('/{order}/checkout', [OrderManagementController::class, 'processCheckout'])->name('checkout.store');
        Route::post('/{order}/transfer', [OrderManagementController::class, 'transferOrder'])->name('transfer');
        Route::put('/{order}/split', [OrderManagementController::class, 'updateSplit'])->name('split');
        Route::post('/{order}/close', [OrderManagementController::class, 'closeOrder'])->name('close');
        Route::get('/{order}/kitchen-ticket', [OrderManagementController::class, 'printKitchenTicket'])->name('kitchen-ticket');
    });

    // API para verificar roles y permisos
    Route::get('/api/user', [AuthController::class, 'getCurrentUser'])->name('api.user');
    Route::get('/api/user/has-role/{role}', [AuthController::class, 'hasRole'])->name('api.user.has-role');
    Route::get('/api/user/has-permission/{permission}', [AuthController::class, 'hasPermission'])->name('api.user.has-permission');

    // Rutas POS
    Route::prefix('pos')->name('pos.')->group(function () {
        Route::redirect('/', '/orders')->name('index');
        Route::get('/sales-history', [\App\Http\Controllers\POS\POSController::class, 'salesHistory'])->name('sales-history.index');
        Route::get('/sales/{sale}/print', [\App\Http\Controllers\POS\InvoiceController::class, 'printSale'])->name('sales.print');
        Route::get('/promo-codes/create', [\App\Http\Controllers\POS\DiscountController::class, 'create'])->name('promo-codes.create');
        Route::post('/promo-codes', [\App\Http\Controllers\POS\DiscountController::class, 'store'])->name('promo-codes.store');
        
        // Productos
        Route::get('/api/products', [\App\Http\Controllers\POS\ProductController::class, 'index']);
        Route::get('/api/products/{id}', [\App\Http\Controllers\POS\ProductController::class, 'show']);
        
        // Ventas
        Route::post('/api/sales', [\App\Http\Controllers\POS\SaleController::class, 'store']);
        Route::get('/api/sales/{id}', [\App\Http\Controllers\POS\SaleController::class, 'show']);
        Route::get('/api/sales', [\App\Http\Controllers\POS\SaleController::class, 'index']);
        
        // Pagos
        Route::post('/api/payments', [\App\Http\Controllers\POS\PaymentController::class, 'store']);
        Route::get('/api/payment-methods', [\App\Http\Controllers\POS\PaymentController::class, 'getMethods']);
        
        // Facturas
        Route::post('/api/invoices', [\App\Http\Controllers\POS\InvoiceController::class, 'store']);
        Route::get('/api/invoices/{id}', [\App\Http\Controllers\POS\InvoiceController::class, 'show']);
        
        // Cajas
        Route::post('/api/boxes/{id}/open', [\App\Http\Controllers\POS\BoxController::class, 'open']);
        Route::post('/api/boxes/{id}/close', [\App\Http\Controllers\POS\BoxController::class, 'close']);
        Route::get('/api/boxes', [\App\Http\Controllers\POS\BoxController::class, 'index']);
        
        // Descuentos y promociones
        Route::get('/api/discounts', [\App\Http\Controllers\POS\DiscountController::class, 'index']);
        Route::post('/api/validate-coupon', [\App\Http\Controllers\POS\DiscountController::class, 'validateCoupon']);
    });
});
