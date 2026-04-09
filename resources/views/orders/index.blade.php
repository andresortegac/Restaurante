@extends('layouts.app')

@section('title', 'Pedidos - RestaurantePOS')

@section('content')
    @php
        $user = Auth::user();
        $canManageOrders = $user->hasRole('Admin') || $user->hasAnyPermission(['orders.create', 'orders.edit']);
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
        $activeOrderTables = $tables->filter(fn ($restaurantTable) => $restaurantTable->openOrder)->values();
    @endphp

    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Pedidos por Mesa / RF-08 al RF-10</span>
                <h1>Pedidos de salon</h1>
                <p>Selecciona la mesa a la que atiende el mesero, toma el pedido en sitio y envia la comanda a cocina desde este modulo.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">{{ $summary['total'] }} mesas activas</span>
                <span class="summary-chip">{{ $summary['openOrders'] }} pedidos abiertos</span>
                <span class="summary-chip">{{ $summary['free'] }} mesas libres</span>
            </div>
        </section>

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="order-summary-card h-100">
                    <div class="summary-kicker">Pedidos abiertos</div>
                    <div class="summary-value">{{ $summary['openOrders'] }}</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="order-summary-card h-100">
                    <div class="summary-kicker">Mesas libres</div>
                    <div class="summary-value">{{ $summary['free'] }}</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="order-summary-card h-100">
                    <div class="summary-kicker">Mesas ocupadas</div>
                    <div class="summary-value">{{ $summary['occupied'] }}</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="order-summary-card h-100">
                    <div class="summary-kicker">Mesas reservadas</div>
                    <div class="summary-value">{{ $summary['reserved'] }}</div>
                </div>
            </div>
        </div>

        <div class="module-toolbar">
            <div>
                <h5 class="mb-1">Mesas disponibles para el servicio</h5>
                <p class="table-note mb-0">Abre la mesa para crear un pedido nuevo o continuar el que ya este en curso.</p>
            </div>
            <div class="table-card-actions">
                <a href="{{ route('orders.index') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-rotate-right"></i> Actualizar
                </a>
                <a href="{{ route('tables.index') }}" class="btn btn-outline-primary">
                    <i class="fas fa-chair"></i> Ver mesas
                </a>
            </div>
        </div>

        @if($tables->isEmpty())
            <div class="card module-card">
                <div class="card-body">
                    <div class="empty-state">
                        <i class="fas fa-receipt"></i>
                        <h5 class="mb-2">No hay mesas activas para tomar pedidos</h5>
                        <p class="mb-0">Configura las mesas desde el modulo de Mesas para empezar a registrar pedidos de salon.</p>
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

                    <article class="table-card">
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
                                <div class="summary-kicker">Pedido</div>
                                @if($openOrder)
                                    <div class="fw-bold">{{ $openOrder->order_number }}</div>
                                    <div class="seat-note">{{ $itemsCount }} items registrados</div>
                                @else
                                    <div class="fw-bold">Sin pedido abierto</div>
                                    <div class="seat-note">Lista para tomar orden</div>
                                @endif
                            </div>
                            <div class="meta-box">
                                <div class="summary-kicker">Cliente</div>
                                <div class="fw-bold">{{ $openOrder?->customer_name ?: 'Sin referencia' }}</div>
                                <div class="seat-note">{{ $openOrder ? 'Pedido en curso' : 'Aun no se ha iniciado servicio' }}</div>
                            </div>
                            <div class="meta-box">
                                <div class="summary-kicker">Total actual</div>
                                <div class="fw-bold">${{ number_format((float) ($openOrder?->total ?? 0), 2) }}</div>
                                <div class="seat-note">{{ $openOrder ? 'Incluye impuesto' : 'Sin consumo registrado' }}</div>
                            </div>
                        </div>

                        <div class="table-card-actions">
                            <a href="{{ route('orders.show', $restaurantTable) }}" class="btn btn-primary btn-sm">
                                {{ $openOrder ? 'Continuar pedido' : 'Tomar pedido' }}
                            </a>

                            <a href="{{ route('tables.show', $restaurantTable) }}" class="btn btn-outline-secondary btn-sm">
                                Ver mesa
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif

        <section class="mt-4" id="active-orders">
            <div class="card module-card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h5 class="mb-1">Pedidos activos y cocina</h5>
                        <p class="table-note mb-0">Accede rapido a las mesas que tienen comandas abiertas y reimprime la comanda cuando haga falta.</p>
                    </div>
                </div>
                <div class="card-body">
                    @if($activeOrderTables->isEmpty())
                        <div class="empty-state py-4">
                            <i class="fas fa-fire-burner"></i>
                            <h5 class="mb-2">No hay pedidos activos</h5>
                            <p class="mb-0">Cuando un mesero registre un pedido, aparecera aqui para continuar el servicio o volver a imprimir cocina.</p>
                        </div>
                    @else
                        <div class="tables-grid">
                            @foreach($activeOrderTables as $serviceTable)
                                @php
                                    $serviceOrder = $serviceTable->openOrder;
                                @endphp

                                <div class="table-card">
                                    <div class="table-card-header">
                                        <div>
                                            <div class="summary-kicker">{{ $serviceTable->area ?: 'Salon principal' }}</div>
                                            <h5 class="mb-1">{{ $serviceTable->name }}</h5>
                                            <div class="seat-note">{{ $serviceOrder->order_number }}</div>
                                        </div>
                                        <span class="status-pill status-occupied">En cocina</span>
                                    </div>

                                    <div class="meta-box mb-3">
                                        <div class="summary-kicker">Resumen</div>
                                        <div class="fw-bold">{{ $serviceOrder->items->sum('quantity') }} items</div>
                                        <div class="seat-note">${{ number_format((float) $serviceOrder->total, 2) }} acumulados</div>
                                    </div>

                                    <div class="table-card-actions">
                                        <a href="{{ route('orders.show', $serviceTable) }}" class="btn btn-primary btn-sm">
                                            Abrir servicio
                                        </a>
                                        @if($canManageOrders)
                                            <a href="{{ route('orders.kitchen-ticket', $serviceOrder) }}" target="_blank" class="btn btn-outline-primary btn-sm">
                                                Imprimir cocina
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </section>
    </div>
@endsection
