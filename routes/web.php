<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// Rutas públicas
Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Rutas de autenticación
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

    // API para verificar roles y permisos
    Route::get('/api/user', [AuthController::class, 'getCurrentUser'])->name('api.user');
    Route::get('/api/user/has-role/{role}', [AuthController::class, 'hasRole'])->name('api.user.has-role');
    Route::get('/api/user/has-permission/{permission}', [AuthController::class, 'hasPermission'])->name('api.user.has-permission');

    // Rutas POS
    Route::prefix('pos')->name('pos.')->group(function () {
        Route::get('/', [\App\Http\Controllers\POS\POSController::class, 'index'])->name('index');
        
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

