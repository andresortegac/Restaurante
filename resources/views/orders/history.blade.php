@extends('layouts.app')

@section('title', 'Historial de pedidos - RestaurantePOS')

@section('content')
    @php
        $statusLabels = [
            'open' => 'Abierto',
            'paid' => 'Pagado',
            'cancelled' => 'Cancelado',
        ];
        $statusClasses = [
            'open' => 'text-bg-primary',
            'paid' => 'text-bg-success',
            'cancelled' => 'text-bg-secondary',
        ];
    @endphp

    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Pedidos por Mesa / RF-08 al RF-10</span>
                <h1>Historial de pedidos</h1>
                <p>Consulta los pedidos ya registrados, filtra por mesa o estado y vuelve rapido al servicio o a la factura cuando haga falta.</p>
            </div>
            <div class="summary-group">
                <a href="{{ route('orders.index') }}" class="btn btn-primary">
                    <i class="fas fa-receipt"></i> Volver a pedidos
                </a>
            </div>
        </section>

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

        <div class="card module-card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('orders.history.index') }}">
                    <div class="row g-3">
                        <div class="col-lg-5">
                            <label class="form-label" for="search">Buscar pedido</label>
                            <input
                                type="text"
                                class="form-control"
                                id="search"
                                name="search"
                                value="{{ $filters['search'] ?? '' }}"
                                placeholder="Numero, cliente o nota"
                            >
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label" for="status">Estado</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">Todos</option>
                                @foreach($statusLabels as $statusValue => $statusLabel)
                                    <option value="{{ $statusValue }}" @selected(($filters['status'] ?? '') === $statusValue)>{{ $statusLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <label class="form-label" for="table_id">Mesa</label>
                            <select class="form-select" id="table_id" name="table_id">
                                <option value="">Todas las mesas</option>
                                @foreach($tables as $table)
                                    <option value="{{ $table->id }}" @selected((string) ($filters['table_id'] ?? '') === (string) $table->id)>
                                        {{ $table->name }} - {{ $table->code }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="{{ route('orders.history.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filtrar historial
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card module-card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h5 class="mb-1">Pedidos registrados</h5>
                    <p class="table-note mb-0">Revisa el contexto de cada pedido y entra al flujo que necesites desde aqui.</p>
                </div>
                <span class="summary-chip">{{ number_format($orders->total()) }} registros</span>
            </div>
            <div class="card-body">
                @if($orders->isEmpty())
                    <div class="empty-state">
                        <i class="fas fa-clock-rotate-left"></i>
                        <h5 class="mb-2">No hay pedidos para mostrar</h5>
                        <p class="mb-0">Cuando registres pedidos de mesa apareceran aqui para consultarlos despues.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Pedido</th>
                                    <th>Fecha</th>
                                    <th>Mesa</th>
                                    <th>Cliente</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($orders as $order)
                                    @php
                                        $table = $order->table;
                                        $canOpenService = $order->status === 'open' && $table && $table->is_active;
                                    @endphp
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
                                            <strong>{{ $table?->name ?? 'Mesa sin referencia' }}</strong>
                                            <div class="table-note">{{ $table?->area ?: 'Sin area' }}</div>
                                            @if($order->previousTable)
                                                <div class="table-note">Transferido desde {{ $order->previousTable->name }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <strong>{{ $order->customer?->name ?: $order->customer_name ?: 'Sin cliente' }}</strong>
                                            <div class="table-note">{{ $order->notes ?: 'Sin notas registradas.' }}</div>
                                        </td>
                                        <td>{{ number_format($order->items_count) }}</td>
                                        <td>
                                            <strong>${{ number_format((float) $order->total, 2) }}</strong>
                                            <div class="table-note">Subtotal ${{ number_format((float) $order->subtotal, 2) }}</div>
                                        </td>
                                        <td>
                                            <span class="badge rounded-pill {{ $statusClasses[$order->status] ?? 'text-bg-secondary' }}">
                                                {{ $statusLabels[$order->status] ?? ucfirst($order->status) }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="table-actions">
                                                @if($canOpenService)
                                                    <a href="{{ route('orders.show', $table) }}" class="btn btn-sm btn-primary">
                                                        Abrir servicio
                                                    </a>
                                                @elseif($table)
                                                    <a href="{{ route('tables.show', $table) }}" class="btn btn-sm btn-outline-secondary">
                                                        Ver mesa
                                                    </a>
                                                @endif

                                                @if($order->sale)
                                                    <a href="{{ route('pos.sales.print', $order->sale) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-print"></i> Imprimir factura
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
