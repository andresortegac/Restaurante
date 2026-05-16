@extends('layouts.app')

@section('title', 'Mesas - RestaurantePOS')

@section('content')
    @php
        $user = Auth::user();
        $canCreateTable = $user->hasRole('Admin') || $user->hasPermission('tables.create');
        $canEditTable = $user->hasRole('Admin') || $user->hasPermission('tables.edit');
        $canDeleteTable = $user->hasRole('Admin') || $user->hasPermission('tables.delete');
        $canManageOrders = $user->hasRole('Admin') || $user->hasAnyPermission(['orders.view', 'orders.create', 'orders.edit']);
    @endphp

    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Gestion de Mesas</span>
                <h1>Configuracion de mesas</h1>
                <p>Crea, edita y organiza las mesas del salon. La toma de pedidos para meseros ahora se realiza desde el modulo lateral de <strong>Pedidos</strong>.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">{{ $summary['total'] }} mesas activas</span>
                <span class="summary-chip">{{ $summary['occupied'] }} ocupadas</span>
                <span class="summary-chip">{{ $summary['reserved'] }} reservadas</span>
            </div>
        </section>

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="order-summary-card h-100">
                    <div class="summary-kicker">Mesas activas</div>
                    <div class="summary-value">{{ $summary['total'] }}</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="order-summary-card h-100">
                    <div class="summary-kicker">Libres</div>
                    <div class="summary-value">{{ $summary['free'] }}</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="order-summary-card h-100">
                    <div class="summary-kicker">Ocupadas</div>
                    <div class="summary-value">{{ $summary['occupied'] }}</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="order-summary-card h-100">
                    <div class="summary-kicker">Reservadas</div>
                    <div class="summary-value">{{ $summary['reserved'] }}</div>
                </div>
            </div>
        </div>

        <div class="module-toolbar">
            <div>
                <h5 class="mb-1">Catalogo del salon</h5>
                <p class="table-note mb-0">Cada tarjeta muestra la configuracion de la mesa y un acceso rapido al flujo de pedidos cuando el usuario tiene permisos.</p>
            </div>
            <div class="table-card-actions">
                @if($canManageOrders)
                    <a href="{{ route('orders.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-receipt"></i> Ir a pedidos
                    </a>
                @endif
                @if($canCreateTable)
                    <a href="{{ route('tables.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Nueva mesa
                    </a>
                @endif
            </div>
        </div>

        @if($tables->isEmpty())
            <div class="card module-card">
                <div class="card-body">
                    <div class="empty-state">
                        <i class="fas fa-chair"></i>
                        <h5 class="mb-2">Todavia no hay mesas configuradas</h5>
                        <p class="mb-0">Crea la primera mesa para dejar listo el salon y luego tomar pedidos desde el modulo correspondiente.</p>
                    </div>
                </div>
            </div>
        @else
            <div class="tables-grid tables-grid-compact">
                @foreach($tables as $restaurantTable)
                    @php
                        $openOrder = $restaurantTable->openOrder;
                    @endphp

                    <article class="table-card {{ $restaurantTable->is_active ? '' : 'inactive' }}">
                        <div class="table-card-header">
                            <div>
                                <div class="summary-kicker">{{ $restaurantTable->area ?: 'Salon principal' }}</div>
                                <h4 class="mb-1"><i class="fas fa-chair"></i> {{ $restaurantTable->name }}</h4>
                            </div>
                        </div>

                        <div class="table-card-actions">
                            <a href="{{ route('tables.show', $restaurantTable) }}" class="btn btn-outline-primary btn-sm">
                                Ver detalle
                            </a>

                            @if($canManageOrders)
                                <a href="{{ route('orders.show', $restaurantTable) }}" class="btn btn-primary btn-sm">
                                    {{ $openOrder ? 'Continuar pedido' : 'Tomar pedido' }}
                                </a>
                            @endif

                            @if($canEditTable)
                                <a href="{{ route('tables.edit', $restaurantTable) }}" class="btn btn-outline-primary btn-sm">
                                    Editar
                                </a>
                            @endif

                            @if($canDeleteTable)
                                <form method="POST" action="{{ route('tables.destroy', $restaurantTable) }}" onsubmit="return confirm('Deseas eliminar esta mesa?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm">Eliminar</button>
                                </form>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
@endsection
