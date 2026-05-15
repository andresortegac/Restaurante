@extends('layouts.app')

@section('title', 'Historial de ' . $restaurantTable->name . ' - RestaurantePOS')

@section('content')
    @php
        $user = Auth::user();
        $canManageOrders = $user->hasRole('Admin') || $user->hasAnyPermission(['orders.view', 'orders.create', 'orders.edit']);
        $tableStatusLabels = [
            'free' => 'Libre',
            'occupied' => 'Ocupada',
            'reserved' => 'Reservada',
        ];
        $orderStatusLabels = [
            'open' => 'Abierto',
            'paid' => 'Pagado',
            'cancelled' => 'Cancelado',
        ];
        $orderStatusClasses = [
            'open' => 'text-bg-primary',
            'paid' => 'text-bg-success',
            'cancelled' => 'text-bg-secondary',
        ];
        $statusLabel = $tableStatusLabels[$restaurantTable->status] ?? ucfirst($restaurantTable->status);
    @endphp

    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Gestion de Mesas / Historial</span>
                <h1>{{ $restaurantTable->name }}</h1>
                <p>Consulta todos los pedidos registrados para esta mesa y retoma el servicio cuando exista una cuenta abierta.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">Codigo {{ $restaurantTable->code }}</span>
                <span class="summary-chip">{{ $restaurantTable->area ?: 'Salon principal' }}</span>
                <span class="summary-chip">Capacidad {{ $restaurantTable->capacity }}</span>
                <span class="summary-chip">{{ $statusLabel }}</span>
            </div>
        </section>

        <div class="module-toolbar">
            <div>
                <h5 class="mb-1">Historial de {{ $restaurantTable->name }}</h5>
                <p class="table-note mb-0">Desde aqui puedes revisar los pedidos atendidos por esta mesa y volver al servicio si todavia hay una cuenta en curso.</p>
            </div>
            <div class="table-card-actions">
                <a href="{{ route('tables.history.index') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-list"></i> Volver al listado
                </a>
                <a href="{{ route('tables.show', $restaurantTable) }}" class="btn btn-outline-primary">
                    Ver mesa
                </a>
                @if($canManageOrders && $openOrder)
                    <a href="{{ route('orders.show', $restaurantTable) }}" class="btn btn-primary">
                        Abrir servicio
                    </a>
                @endif
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="order-summary-card h-100">
                    <div class="summary-kicker">Pedidos registrados</div>
                    <div class="summary-value">{{ number_format($summary['total']) }}</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="order-summary-card h-100">
                    <div class="summary-kicker">Pedidos de hoy</div>
                    <div class="summary-value">{{ number_format($summary['today']) }}</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="order-summary-card h-100">
                    <div class="summary-kicker">Abiertos</div>
                    <div class="summary-value">{{ number_format($summary['open']) }}</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="order-summary-card h-100">
                    <div class="summary-kicker">Pagados</div>
                    <div class="summary-value">{{ number_format($summary['paid']) }}</div>
                </div>
            </div>
        </div>

        <div class="card module-card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h5 class="mb-1">Pedidos registrados</h5>
                    <p class="table-note mb-0">Cada registro conserva su cliente, fecha, estado y accesos rapidos segun el flujo disponible.</p>
                </div>
                <span class="summary-chip">{{ number_format($orders->total()) }} registros</span>
            </div>
            <div class="card-body">
                @if($orders->isEmpty())
                    <div class="empty-state">
                        <i class="fas fa-clock-rotate-left"></i>
                        <h5 class="mb-2">Sin historial todavia</h5>
                        <p class="mb-0">Cuando registres pedidos para esta mesa apareceran aqui para su consulta.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Pedido</th>
                                    <th>Fecha</th>
                                    <th>Cliente</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($orders as $order)
                                    <tr>
                                        <td>
                                            <strong>{{ $order->order_number }}</strong>
                                            <div class="table-note">Abierto por {{ $order->openedBy->name ?? 'el equipo' }}</div>
                                        </td>
                                        <td>
                                            {{ $order->created_at?->format('d/m/Y H:i') }}
                                            @if($order->updated_at)
                                                <div class="table-note">Actualizado {{ $order->updated_at->format('d/m/Y H:i') }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <strong>{{ $order->customer?->name ?: $order->customer_name ?: 'Sin cliente' }}</strong>
                                            <div class="table-note">{{ $order->notes ?: 'Sin notas registradas.' }}</div>
                                            @if($order->previousTable)
                                                <div class="table-note">Transferido desde {{ $order->previousTable->name }}</div>
                                            @endif
                                        </td>
                                        <td>{{ number_format($order->items_count) }}</td>
                                        <td>
                                            <strong>${{ number_format((float) $order->total, 2) }}</strong>
                                            <div class="table-note">Subtotal ${{ number_format((float) $order->subtotal, 2) }}</div>
                                        </td>
                                        <td>
                                            <span class="badge rounded-pill {{ $orderStatusClasses[$order->status] ?? 'text-bg-secondary' }}">
                                                {{ $orderStatusLabels[$order->status] ?? ucfirst($order->status) }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="table-actions">
                                                @if($canManageOrders && $order->status === 'open' && $restaurantTable->is_active)
                                                    <a href="{{ route('orders.show', $restaurantTable) }}" class="btn btn-sm btn-primary">
                                                        Abrir servicio
                                                    </a>
                                                @endif

                                                @if($order->sale)
                                                    <a href="{{ route('pos.sales.print', $order->sale) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-print"></i> Imprimir documento
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        {{ $orders->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
