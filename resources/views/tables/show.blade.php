@extends('layouts.app')

@section('title', $restaurantTable->name . ' - Mesas - RestaurantePOS')

@section('content')
    @php
        $user = Auth::user();
        $canEditTable = $user->hasRole('Admin') || $user->hasPermission('tables.edit');
        $canManageOrders = $user->hasRole('Admin') || $user->hasAnyPermission(['orders.view', 'orders.create', 'orders.edit']);
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
        $statusLabel = $statusLabels[$restaurantTable->status] ?? ucfirst($restaurantTable->status);
        $statusClass = $statusClasses[$restaurantTable->status] ?? 'status-free';
    @endphp

    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Gestion de Mesas / RF-06 y RF-07</span>
                <h1>{{ $restaurantTable->name }}</h1>
                <p>Consulta la configuracion de la mesa y su estado actual. La operacion de pedidos, traslados, cuentas e impresion de cocina se realiza desde el modulo <strong>Pedidos</strong>.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">Codigo {{ $restaurantTable->code }}</span>
                <span class="summary-chip">{{ $restaurantTable->area ?: 'Salon principal' }}</span>
                <span class="summary-chip">Capacidad {{ $restaurantTable->capacity }}</span>
                <span class="summary-chip">{{ $statusLabel }}</span>
            </div>
        </section>

        <div class="table-detail-layout">
            <div>
                <div class="card module-card service-card">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div>
                            <h5 class="mb-1">Estado de servicio</h5>
                            <p class="table-note mb-0">
                                @if($openOrder)
                                    Hay un pedido abierto para esta mesa. Puedes continuarlo desde el modulo de pedidos.
                                @else
                                    Esta mesa no tiene un pedido abierto en este momento.
                                @endif
                            </p>
                        </div>
                        <span class="status-pill {{ $statusClass }}">{{ $statusLabel }}</span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="order-summary-card h-100">
                                    <div class="summary-kicker">Mesa</div>
                                    <div class="h3 mb-0">{{ $restaurantTable->code }}</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="order-summary-card h-100">
                                    <div class="summary-kicker">Capacidad</div>
                                    <div class="h3 mb-0">{{ $restaurantTable->capacity }}</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="order-summary-card h-100">
                                    <div class="summary-kicker">Disponibilidad</div>
                                    <div class="h3 mb-0">{{ $restaurantTable->is_active ? 'Activa' : 'Inactiva' }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-lg-6">
                                <div class="meta-box h-100">
                                    <div class="summary-kicker">Pedido abierto</div>
                                    @if($openOrder)
                                        <div class="fw-bold">{{ $openOrder->order_number }}</div>
                                        <div class="seat-note">{{ $openOrder->items->sum('quantity') }} items registrados</div>
                                        <div class="table-note mt-2">Cliente: {{ $openOrder->customer_name ?: 'Sin nombre registrado' }}</div>
                                    @else
                                        <div class="fw-bold">Sin pedido en curso</div>
                                        <div class="seat-note">La mesa esta disponible para iniciar servicio.</div>
                                    @endif
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="meta-box h-100">
                                    <div class="summary-kicker">Notas operativas</div>
                                    <div class="seat-note">{{ $restaurantTable->notes ?: 'Sin notas operativas registradas.' }}</div>
                                </div>
                            </div>
                        </div>

                        @if($canManageOrders)
                            <div class="detail-actions mt-4">
                                <a href="{{ route('orders.show', $restaurantTable) }}" class="btn btn-primary">
                                    {{ $openOrder ? 'Ir al pedido de esta mesa' : 'Tomar pedido desde pedidos' }}
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <aside>
                <div class="card module-card service-card">
                    <div class="card-header">
                        <h5 class="mb-0">Acciones</h5>
                    </div>
                    <div class="card-body">
                        <div class="detail-actions">
                            <a href="{{ route('tables.index') }}" class="btn btn-outline-secondary">Volver a mesas</a>

                            @if($canEditTable)
                                <a href="{{ route('tables.edit', $restaurantTable) }}" class="btn btn-outline-primary">Editar mesa</a>
                            @endif

                            @if($canManageOrders)
                                <a href="{{ route('orders.show', $restaurantTable) }}" class="btn btn-primary">Abrir pedidos</a>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="card module-card service-card">
                    <div class="card-header">
                        <h5 class="mb-0">Historial de pedidos</h5>
                    </div>
                    <div class="card-body">
                        <div class="history-list">
                            @forelse($recentOrders as $recentOrder)
                                <div class="history-item">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div>
                                            <strong>{{ $recentOrder->order_number }}</strong>
                                            <div class="seat-note">{{ $recentOrder->created_at->format('d/m/Y H:i') }}</div>
                                        </div>
                                        <span class="badge rounded-pill {{ $recentOrder->status === 'open' ? 'text-bg-primary' : ($recentOrder->status === 'paid' ? 'text-bg-success' : 'text-bg-secondary') }}">
                                            {{ $recentOrder->status === 'open' ? 'Abierto' : ($recentOrder->status === 'paid' ? 'Pagado' : 'Cancelado') }}
                                        </span>
                                    </div>

                                    <div class="mt-3">
                                        <div class="summary-kicker">Cliente</div>
                                        <div class="seat-note">{{ $recentOrder->customer_name ?: 'Sin nombre registrado' }}</div>
                                    </div>

                                    <div class="mt-3 d-flex justify-content-between gap-3">
                                        <div>
                                            <div class="summary-kicker">Total</div>
                                            <div class="fw-bold">${{ number_format((float) $recentOrder->total, 2) }}</div>
                                        </div>
                                        <div class="text-end">
                                            <div class="summary-kicker">Items</div>
                                            <div class="fw-bold">{{ $recentOrder->items->sum('quantity') }}</div>
                                        </div>
                                    </div>

                                    @if($recentOrder->previousTable)
                                        <div class="table-note mt-3">Transferido desde {{ $recentOrder->previousTable->name }}.</div>
                                    @endif
                                </div>
                            @empty
                                <div class="empty-state py-4">
                                    <i class="fas fa-clock-rotate-left"></i>
                                    <h5 class="mb-2">Sin historial todavia</h5>
                                    <p class="mb-0">Cuando esta mesa tenga pedidos registrados, apareceran en este panel.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
@endsection
