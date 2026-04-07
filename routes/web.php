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
});

