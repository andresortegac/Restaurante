@extends('layouts.app')

@section('title', 'Historial por mesa - RestaurantePOS')

@section('content')
    @php
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
    @endphp

    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Gestion de Mesas / Historial</span>
                <h1>Historial de pedidos por mesa</h1>
                <p>Selecciona una mesa creada para consultar sus pedidos registrados y entrar rapido al historial correspondiente.</p>
            </div>
            <div class="summary-group">
                <a href="{{ route('tables.index') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-chair"></i> Ver mesas
                </a>
            </div>
        </section>

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="order-summary-card h-100">
                    <div class="summary-kicker">Mesas creadas</div>
                    <div class="summary-value">{{ number_format($summary['tables']) }}</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="order-summary-card h-100">
                    <div class="summary-kicker">Con historial</div>
                    <div class="summary-value">{{ number_format($summary['withHistory']) }}</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="order-summary-card h-100">
                    <div class="summary-kicker">Pedidos registrados</div>
                    <div class="summary-value">{{ number_format($summary['orders']) }}</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="order-summary-card h-100">
                    <div class="summary-kicker">Abiertos</div>
                    <div class="summary-value">{{ number_format($summary['open']) }}</div>
                </div>
            </div>
        </div>

        @if($tables->isEmpty())
            <div class="card module-card">
                <div class="card-body">
                    <div class="empty-state">
                        <i class="fas fa-clock-rotate-left"></i>
                        <h5 class="mb-2">Todavia no hay mesas creadas</h5>
                        <p class="mb-0">Crea mesas para comenzar a gestionar el historial de pedidos por cada una.</p>
                    </div>
                </div>
            </div>
        @else
            <div class="module-toolbar">
                <div>
                    <h5 class="mb-1">Mesas registradas</h5>
                    <p class="table-note mb-0">Cada mesa tiene un acceso directo para revisar su historial de pedidos.</p>
                </div>
            </div>

            <div class="tables-grid tables-grid-compact">
                @foreach($tables as $restaurantTable)
                    @php
                        $statusLabel = $statusLabels[$restaurantTable->status] ?? ucfirst($restaurantTable->status);
                        $statusClass = $statusClasses[$restaurantTable->status] ?? 'status-free';
                        $latestOrder = $restaurantTable->latestOrder;
                        $openOrder = $restaurantTable->openOrder;
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
                                <div class="summary-kicker">Pedidos registrados</div>
                                <div class="h4 mb-0">{{ number_format($restaurantTable->orders_count) }}</div>
                                <div class="seat-note">{{ number_format($restaurantTable->paid_orders_count) }} pagados</div>
                            </div>
                            <div class="meta-box">
                                <div class="summary-kicker">Ultimo pedido</div>
                                @if($latestOrder)
                                    <div class="fw-bold">{{ $latestOrder->order_number }}</div>
                                    <div class="seat-note">{{ $latestOrder->created_at?->format('d/m/Y H:i') }}</div>
                                @else
                                    <div class="fw-bold">Sin pedidos</div>
                                    <div class="seat-note">Aun no registra historial</div>
                                @endif
                            </div>
                            <div class="meta-box">
                                <div class="summary-kicker">Servicio actual</div>
                                @if($openOrder)
                                    <div class="fw-bold">{{ $openOrder->order_number }}</div>
                                    <div class="seat-note">Pedido abierto en curso</div>
                                @else
                                    <div class="fw-bold">Sin pedido abierto</div>
                                    <div class="seat-note">Mesa disponible para servicio</div>
                                @endif
                            </div>
                            <div class="meta-box">
                                <div class="summary-kicker">Capacidad</div>
                                <div class="fw-bold">{{ $restaurantTable->capacity }} personas</div>
                                <div class="seat-note">{{ $restaurantTable->is_active ? 'Mesa activa' : 'Mesa inactiva' }}</div>
                            </div>
                        </div>

                        <div class="table-card-actions">
                            <a href="{{ route('tables.history.show', $restaurantTable) }}" class="btn btn-primary btn-sm">
                                Ver historial de pedidos
                            </a>
                            <a href="{{ route('tables.show', $restaurantTable) }}" class="btn btn-outline-primary btn-sm">
                                Ver detalle de la mesa
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
@endsection
