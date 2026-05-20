@extends('layouts.app')

@section('title', 'Dashboard - RestaurantePOS')

@section('content')
    @php
        $currentAccess = $accountUser->current_login_at;
        $previousAccess = $accountUser->previous_login_at;
    @endphp

    <div class="dashboard-panel">
        <div class="welcome-section">
            <h1><i class="fas fa-wave-hand"></i> Bienvenido, {{ $accountUser->name }}!</h1>
            <p>
                Has iniciado sesion correctamente en el Sistema de Gestion de Restaurantes.
                Aqui puedes consultar el estado operativo del negocio y seguir el movimiento del dia en tiempo real.
            </p>
        </div>

        <div class="dashboard-grid">
            <div class="stat-card">
                <h3><i class="fas fa-utensils"></i> Pedidos de mesa hoy</h3>
                <div class="value">{{ number_format($stats['table_orders_today']) }}</div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-motorcycle"></i> Domicilios hoy</h3>
                <div class="value">{{ number_format($stats['deliveries_today']) }}</div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-calendar-check"></i> Reservas hoy</h3>
                <div class="value">{{ number_format($stats['reservations_today']) }}</div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-chair"></i> Mesas Ocupadas</h3>
                <div class="value">{{ number_format($stats['occupied_tables']) }}</div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-check-circle"></i> Mesas disponibles</h3>
                <div class="value">{{ number_format($stats['available_tables']) }}</div>
            </div>
            @if($canViewFinancialStats)
                <div class="stat-card">
                    <h3><i class="fas fa-dollar-sign"></i> Ventas Hoy</h3>
                    <div class="value">${{ number_format($stats['sales_today'], 2) }}</div>
                </div>
                <div class="stat-card">
                    <h3><i class="fas fa-chart-line"></i> Ingresos mensuales</h3>
                    <div class="value">${{ number_format($stats['monthly_income'], 2) }}</div>
                </div>
            @endif
            <div class="stat-card">
                <h3><i class="fas fa-users"></i> Clientes</h3>
                <div class="value">{{ number_format($stats['customers']) }}</div>
            </div>
        </div>

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
                                <td>{{ $accountUser->name }}</td>
                            </tr>
                            <tr>
                                <td><strong>Email:</strong></td>
                                <td>{{ $accountUser->email }}</td>
                            </tr>
                            <tr>
                                <td><strong>Acceso actual:</strong></td>
                                <td>{{ $currentAccess ? $currentAccess->format('d/m/Y H:i') : 'Sin registro' }}</td>
                            </tr>
                            <tr>
                                <td><strong>Acceso anterior:</strong></td>
                                <td>{{ $previousAccess ? $previousAccess->format('d/m/Y H:i') : 'Sin registro anterior' }}</td>
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
                        <div class="dashboard-role-list">
                            @forelse($roles as $role)
                                <span class="badge-role">
                                    <i class="fas fa-user-shield"></i> {{ $role->name }}
                                    @if($role->description)
                                        <small class="text-muted d-block">{{ $role->description }}</small>
                                    @endif
                                </span>
                            @empty
                                <p class="text-muted mb-0">Sin roles asignados.</p>
                            @endforelse
                        </div>
                        <small class="text-muted d-block">Tienes <strong>{{ number_format($permissionsCount) }}</strong> permisos activos</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
