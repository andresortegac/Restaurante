@extends('layouts.app')

@section('title', 'Facturación - RestaurantePOS')

@section('content')
    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Caja / Facturación</span>
                <h1>Cuentas por cobrar</h1>
                <p>El cajero puede ver las mesas con pedidos abiertos, revisar el total pendiente y entrar directo al cobro.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">{{ number_format($summary['openOrders']) }} cuentas abiertas</span>
                <span class="summary-chip">{{ number_format($summary['tables']) }} mesas</span>
                <span class="summary-chip">${{ number_format($summary['totalDue'], 2) }} por cobrar</span>
            </div>
        </section>

        <div class="card module-card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('billing.index') }}">
                    <div class="row g-3">
                        <div class="col-lg-8">
                            <label class="form-label" for="search">Buscar</label>
                            <input type="text" class="form-control" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Pedido, mesa, código o cliente">
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label" for="area">Área</label>
                            <select class="form-select" id="area" name="area">
                                <option value="">Todas</option>
                                @foreach($areas as $area)
                                    <option value="{{ $area }}" @selected(($filters['area'] ?? '') === $area)>{{ $area }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="{{ route('billing.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filtrar cuentas
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card module-card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h5 class="mb-1">Mesas pendientes de cobro</h5>
                    <p class="table-note mb-0">Solo aparecen pedidos abiertos que todavía no generan venta.</p>
                </div>
                <span class="summary-chip">{{ number_format($openOrders->total()) }} registros</span>
            </div>
            <div class="card-body">
                @if($openOrders->isEmpty())
                    <div class="empty-state">
                        <i class="fas fa-cash-register"></i>
                        <h5 class="mb-2">No hay cuentas pendientes</h5>
                        <p class="mb-0">Cuando haya mesas listas para cobrar aparecerán aquí.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Pedido</th>
                                    <th>Mesa</th>
                                    <th>Cliente</th>
                                    <th>Items</th>
                                    <th>Abierto</th>
                                    <th>Total</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($openOrders as $order)
                                    <tr>
                                        <td>
                                            <strong>{{ $order->order_number }}</strong>
                                            <div class="table-note">Atendido por {{ $order->openedBy?->name ?? 'el equipo' }}</div>
                                        </td>
                                        <td>
                                            <strong>{{ $order->table?->name ?? 'Mesa no disponible' }}</strong>
                                            <div class="table-note">{{ $order->table?->code ?? 'Sin código' }}{{ $order->table?->area ? ' · ' . $order->table->area : '' }}</div>
                                        </td>
                                        <td>
                                            <strong>{{ $order->customer?->name ?: $order->customer_name ?: 'Sin cliente' }}</strong>
                                            <div class="table-note">{{ $order->notes ?: 'Sin notas registradas.' }}</div>
                                        </td>
                                        <td>{{ number_format($order->items_count) }}</td>
                                        <td>
                                            {{ $order->created_at?->format('d/m/Y H:i') }}
                                            @if($order->updated_at)
                                                <div class="table-note">Actualizado {{ $order->updated_at->format('H:i') }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <strong>${{ number_format((float) $order->total, 2) }}</strong>
                                            <div class="table-note">Subtotal ${{ number_format((float) $order->subtotal, 2) }}</div>
                                        </td>
                                        <td>
                                            <div class="table-actions justify-content-end">
                                                <a href="{{ route('billing.checkout', $order) }}" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-cash-register"></i> Cobrar
                                                </a>
                                                @if($order->table)
                                                    <a href="{{ route('orders.show', $order->table) }}" class="btn btn-outline-secondary btn-sm">
                                                        Ver pedido
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
                        {{ $openOrders->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
