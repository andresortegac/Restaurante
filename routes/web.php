<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrderManagementController;
use App\Http\Controllers\ProductManagementController;
use App\Http\Controllers\TableManagementController;
use Illuminate\Support\Facades\Route;

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

    // Dashboard
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // Gestion de productos
    Route::prefix('products')->name('products.')->group(function () {
        Route::get('/menu', [ProductManagementController::class, 'menu'])->name('menu.index');
        Route::get('/menu/create', [ProductManagementController::class, 'createMenuProduct'])->name('menu.create');
        Route::post('/menu', [ProductManagementController::class, 'storeMenuProduct'])->name('menu.store');
        Route::get('/menu/{product}/edit', [ProductManagementController::class, 'editMenuProduct'])->name('menu.edit');
        Route::put('/menu/{product}', [ProductManagementController::class, 'updateMenuProduct'])->name('menu.update');
        Route::delete('/menu/{product}', [ProductManagementController::class, 'destroyMenuProduct'])->name('menu.destroy');

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
        Route::get('/tables/{table}', [OrderManagementController::class, 'show'])->name('show');
        Route::post('/tables/{table}', [OrderManagementController::class, 'storeOrder'])->name('store');
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
        Route::get('/', [\App\Http\Controllers\POS\POSController::class, 'index'])->name('index');
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


