@extends('layouts.app')

@section('title', 'Historial de Ventas - POS')

@section('content')
<div class="module-page">
    <section class="module-hero">
        <div>
            <span class="module-kicker">Punto de Venta</span>
            <h1>Historial de ventas</h1>
            <p>Consulta las ventas registradas, revisa su total y vuelve a imprimir la factura cuando lo necesites.</p>
        </div>
        <div class="summary-group">
            <a href="{{ route('pos.index') }}" class="btn btn-primary">
                <i class="fas fa-store"></i> Volver al POS
            </a>
        </div>
    </section>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card module-card h-100">
                <div class="card-body">
                    <div class="summary-kicker">Ventas de hoy</div>
                    <div class="summary-value">{{ number_format($todaySales) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card module-card h-100">
                <div class="card-body">
                    <div class="summary-kicker">Ingresos de hoy</div>
                    <div class="summary-value">${{ number_format($todayRevenue, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card module-card h-100">
                <div class="card-body">
                    <div class="summary-kicker">Facturas pendientes</div>
                    <div class="summary-value">{{ number_format($pendingInvoices) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card module-card">
        <div class="card-body">
            @if($sales->isEmpty())
                <div class="empty-state">
                    <i class="fas fa-receipt"></i>
                    <h3>No hay ventas registradas</h3>
                    <p>Cuando completes ventas en el POS apareceran aqui con opcion para reimprimir la factura.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Venta</th>
                                <th>Fecha</th>
                                <th>Factura</th>
                                <th>Vendedor</th>
                                <th>Metodo de pago</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sales as $sale)
                                <tr>
                                    <td>
                                        <strong>#{{ $sale->id }}</strong>
                                        <div class="text-muted small">{{ $sale->box?->name ?? 'Sin caja' }}</div>
                                    </td>
                                    <td>{{ $sale->created_at?->format('d/m/Y H:i') }}</td>
                                    <td>
                                        @if($sale->invoice)
                                            <span class="badge bg-success-subtle text-success border">{{ $sale->invoice->invoice_number }}</span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary border">Pendiente</span>
                                        @endif
                                    </td>
                                    <td>{{ $sale->user?->name ?? 'Sin usuario' }}</td>
                                    <td>{{ $sale->payments->pluck('paymentMethod.name')->filter()->join(', ') ?: 'Sin pago' }}</td>
                                    <td>{{ $sale->items_count }}</td>
                                    <td><strong>${{ number_format((float) $sale->total, 2) }}</strong></td>
                                    <td>
                                        <a href="{{ route('pos.sales.print', $sale) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-print"></i> Imprimir factura
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $sales->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
