@extends('layouts.app')

@section('title', 'Dashboard - RestaurantePOS')

@section('content')
    <!-- Welcome Section -->
    <div class="welcome-section">
        <h1><i class="fas fa-wave-hand"></i> Bienvenido, {{ Auth::user()->name }}!</h1>
        <p>
            Has iniciado sesión correctamente en el Sistema de Gestión de Restaurantes.
            Aquí puedes gestionar todas las operaciones del restaurante.
        </p>
    </div>

    <!-- Dashboard Stats -->
    <div class="dashboard-grid">
        <div class="stat-card">
            <h3><i class="fas fa-clipboard-list"></i> Pedidos Pendientes</h3>
            <div class="value">0</div>
        </div>
        <div class="stat-card">
            <h3><i class="fas fa-chair"></i> Mesas Ocupadas</h3>
            <div class="value">0</div>
        </div>
        <div class="stat-card">
            <h3><i class="fas fa-dollar-sign"></i> Ventas Hoy</h3>
            <div class="value">$0.00</div>
        </div>
        <div class="stat-card">
            <h3><i class="fas fa-users"></i> Clientes</h3>
            <div class="value">0</div>
        </div>
    </div>

    <!-- User Information -->
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-user-circle"></i> Información de Cuenta</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td><strong>Nombre:</strong></td>
                            <td>{{ Auth::user()->name }}</td>
                        </tr>
                        <tr>
                            <td><strong>Email:</strong></td>
                            <td>{{ Auth::user()->email }}</td>
                        </tr>
                        <tr>
                            <td><strong>ltimo acceso:</strong></td>
                            <td>Hace poco</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-shield-alt"></i> Roles y Permisos</h5>
                </div>
                <div class="card-body">
                    <div id="roles-container" style="margin-bottom: 15px;">
                        <p class="text-muted"><i class="fas fa-spinner fa-spin"></i> Cargando roles...</p>
                    </div>
                    <small class="text-muted d-block">Tienes <strong id="permissions-count">0</strong> permisos activos</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Permissions -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-lock"></i> Permisos Autorizados</h5>
        </div>
        <div class="card-body">
            <div id="permissions-container">
                <p class="text-muted"><i class="fas fa-spinner fa-spin"></i> Cargando permisos...</p>
            </div>
        </div>
    </div>

    <!-- Active Sessions -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-history"></i> Estado del Sistema</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <p class="text-center">
                        <strong>Sesión Activa</strong><br>
                        <span class="badge bg-success">En línea</span>
                    </p>
                </div>
                <div class="col-md-3">
                    <p class="text-center">
                        <strong>Base de Datos</strong><br>
                        <span class="badge bg-success">Conectada</span>
                    </p>
                </div>
                <div class="col-md-3">
                    <p class="text-center">
                        <strong>Servidor</strong><br>
                        <span class="badge bg-success">Operativo</span>
                    </p>
                </div>
                <div class="col-md-3">
                    <p class="text-center">
                        <strong>Versión</strong><br>
                        <span class="badge bg-info">v1.0.0</span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom JS para dashboard -->
    <script src="{{ asset('js/dashboard/dashboard.js') }}"></script>
@endsection
