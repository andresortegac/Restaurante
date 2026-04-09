@extends('layouts.app')

@section('title', 'Mesas - RestaurantePOS')

@section('content')
    @php
        $user = Auth::user();
        $canCreateTable = $user->hasRole('Admin') || $user->hasPermission('tables.create');
        $canEditTable = $user->hasRole('Admin') || $user->hasPermission('tables.edit');
        $canDeleteTable = $user->hasRole('Admin') || $user->hasPermission('tables.delete');
        $statusLabels = [
            'free' => 'Libre',
            'occupied' => 'Ocupada',
            'reserved' => 'Reservada',
        ];
        $statusClasses = [
            'free' => 'status-free',
            'occupied' => 'status-occupied',
            'reserved' => 'status-reserved',
        ];
        $serviceTables = $tables->filter(function ($restaurantTable) {
            return $restaurantTable->is_active && ($restaurantTable->status !== 'free' || $restaurantTable->openOrder);
        })->values();
    @endphp

    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Gestion de Mesas / RF-06 al RF-10</span>
                <h1>Mesas, pedidos y cuentas</h1>
                <p>Administra las mesas del salon, visualiza su estado en tiempo real y entra al flujo operativo para asignar pedidos, transferirlos o dividir la cuenta.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">{{ $summary['total'] }} mesas activas</span>
                <span class="summary-chip">{{ $summary['openOrders'] }} pedidos abiertos</span>
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
                <h5 class="mb-1">Vista general del salon</h5>
                <p class="table-note mb-0">Cada tarjeta muestra el estado actual de la mesa y te lleva directo al detalle operativo.</p>
            </div>
            @if($canCreateTable)
                <a href="{{ route('tables.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nueva mesa
                </a>
            @endif
        </div>

        @if($tables->isEmpty())
            <div class="card module-card">
                <div class="card-body">
                    <div class="empty-state">
                        <i class="fas fa-chair"></i>
                        <h5 class="mb-2">Todavia no hay mesas configuradas</h5>
                        <p class="mb-0">Crea la primera mesa para empezar a asignar pedidos desde el salon.</p>
                    </div>
                </div>
            </div>
        @else
            <div class="tables-grid">
                @foreach($tables as $restaurantTable)
                    @php
                        $openOrder = $restaurantTable->openOrder;
                        $statusLabel = $statusLabels[$restaurantTable->status] ?? ucfirst($restaurantTable->status);
                        $statusClass = $statusClasses[$restaurantTable->status] ?? 'status-free';
                        $itemsCount = $openOrder ? $openOrder->items->sum('quantity') : 0;
                    @endphp

                    <article class="table-card {{ $restaurantTable->is_active ? '' : 'inactive' }}">
                        <div class="table-card-header">
                            <div>
                                <div class="summary-kicker">{{ $restaurantTable->area ?: 'Salon principal' }}</div>
                                <h4 class="mb-1">{{ $restaurantTable->name }}</h4>
                                <div class="seat-note">Codigo {{ $restaurantTable->code }}</div>
                            </div>
                            <span class="status-pill {{ $statusClass }}">{{ $statusLabel }}</span>
                        </div>

                        <div class="table-card-meta">
                            <div class="meta-box">
                                <div class="summary-kicker">Capacidad</div>
                                <div class="h4 mb-0">{{ $restaurantTable->capacity }}</div>
                                <div class="seat-note">personas</div>
                            </div>
                            <div class="meta-box">
                                <div class="summary-kicker">Pedido abierto</div>
                                @if($openOrder)
                                    <div class="fw-bold">{{ $openOrder->order_number }}</div>
                                    <div class="seat-note">{{ $itemsCount }} items cargados</div>
                                @else
                                    <div class="fw-bold">Sin pedido</div>
                                    <div class="seat-note">Lista para recibir clientes</div>
                                @endif
                            </div>
                            <div class="meta-box">
                                <div class="summary-kicker">Cuenta actual</div>
                                <div class="fw-bold">${{ number_format((float) ($openOrder?->total ?? 0), 2) }}</div>
                                <div class="seat-note">{{ $openOrder?->customer_name ?: 'Sin cliente asociado' }}</div>
                            </div>
                            <div class="meta-box">
                                <div class="summary-kicker">Disponibilidad</div>
                                <div class="fw-bold">{{ $restaurantTable->is_active ? 'Activa' : 'Inactiva' }}</div>
                                <div class="seat-note">{{ $restaurantTable->notes ?: 'Sin notas internas.' }}</div>
                            </div>
                        </div>

                        <div class="table-card-actions">
                            <a href="{{ route('tables.show', $restaurantTable) }}" class="btn btn-primary btn-sm">
                                Gestionar mesa
                            </a>

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

        <section class="mt-4" id="service-flow">
            <div class="card module-card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h5 class="mb-1">Pedidos y cuentas en curso</h5>
                        <p class="table-note mb-0">Acceso rapido a las mesas reservadas u ocupadas para continuar el servicio.</p>
                    </div>
                    <a href="{{ route('tables.index') }}#service-flow" class="btn btn-outline-secondary btn-sm">Actualizar vista</a>
                </div>
                <div class="card-body">
                    @if($serviceTables->isEmpty())
                        <div class="empty-state py-4">
                            <i class="fas fa-receipt"></i>
                            <h5 class="mb-2">No hay servicio activo en este momento</h5>
                            <p class="mb-0">Cuando una mesa tenga pedido abierto o quede reservada aparecera en este panel.</p>
                        </div>
                    @else
                        <div class="tables-grid">
                            @foreach($serviceTables as $serviceTable)
                                @php
                                    $serviceOrder = $serviceTable->openOrder;
                                    $serviceStatusLabel = $statusLabels[$serviceTable->status] ?? ucfirst($serviceTable->status);
                                    $serviceStatusClass = $statusClasses[$serviceTable->status] ?? 'status-free';
                                @endphp

                                <div class="table-card">
                                    <div class="table-card-header">
                                        <div>
                                            <div class="summary-kicker">{{ $serviceTable->area ?: 'Salon principal' }}</div>
                                            <h5 class="mb-1">{{ $serviceTable->name }}</h5>
                                            <div class="seat-note">Codigo {{ $serviceTable->code }}</div>
                                        </div>
                                        <span class="status-pill {{ $serviceStatusClass }}">{{ $serviceStatusLabel }}</span>
                                    </div>

                                    <div class="meta-box mb-3">
                                        <div class="summary-kicker">Servicio</div>
                                        @if($serviceOrder)
                                            <div class="fw-bold">{{ $serviceOrder->order_number }}</div>
                                            <div class="seat-note">${{ number_format((float) $serviceOrder->total, 2) }} acumulados</div>
                                        @else
                                            <div class="fw-bold">Sin pedido abierto</div>
                                            <div class="seat-note">Mesa en espera o reservada</div>
                                        @endif
                                    </div>

                                    <a href="{{ route('tables.show', $serviceTable) }}#service-flow" class="btn btn-primary w-100">
                                        Abrir flujo de servicio
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </section>
    </div>
@endsection
