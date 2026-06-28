@extends('layouts.app')

@section('title', 'Facturas anuladas - Facturacion')

@section('content')
    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Caja / Facturacion</span>
                <h1>Facturas anuladas</h1>
                <p>Consulta los documentos anulados por administracion e imprime una copia cuando haga falta.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">{{ number_format($summary['voided']) }} anuladas</span>
                <span class="summary-chip">{{ number_format($summary['today']) }} hoy</span>
                <span class="summary-chip">${{ money($summary['total']) }} anulados</span>
                <a href="{{ route('billing.history') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Ventas generales
                </a>
            </div>
        </section>

        <div class="card module-card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('billing.voided') }}">
                    <div class="row g-3 align-items-end">
                        <div class="col-lg-9">
                            <label class="form-label" for="search">Buscar</label>
                            <input type="text" class="form-control" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Venta, factura, cliente, CUFE o motivo">
                        </div>
                        <div class="col-lg-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Buscar
                            </button>
                            <a href="{{ route('billing.voided') }}" class="btn btn-outline-secondary">Limpiar</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card module-card">
            <div class="card-body">
                @if($sales->isEmpty())
                    <div class="empty-state">
                        <i class="fas fa-ban"></i>
                        <h3>No hay facturas anuladas</h3>
                        <p>Cuando un administrador anule una factura, aparecera aqui.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Venta</th>
                                    <th>Factura</th>
                                    <th>Pedido</th>
                                    <th>Cliente / origen</th>
                                    <th>Anulada por</th>
                                    <th>Motivo</th>
                                    <th>Total</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($sales as $sale)
                                    @php($invoice = $sale->invoice)
                                    <tr>
                                        <td>
                                            <strong>#{{ $sale->id }}</strong>
                                            <div class="text-muted small">{{ $sale->created_at?->format('d/m/Y H:i') }}</div>
                                            <div class="text-muted small">{{ $sale->box?->name ?? 'Sin caja' }}</div>
                                        </td>
                                        <td>
                                            @if($invoice)
                                                <strong>{{ $invoice->invoice_number }}</strong>
                                                <div class="text-muted small">{{ $invoice->isElectronic() ? 'Factura electronica' : 'Ticket' }}</div>
                                                <div class="text-muted small">Estado: {{ $invoice->status }}</div>
                                            @else
                                                <span class="text-muted">Sin documento</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($sale->tableOrder)
                                                <strong>{{ $sale->tableOrder->order_number }}</strong>
                                                <div class="text-muted small">{{ $sale->tableOrder->table?->name ?? 'Mesa sin referencia' }}</div>
                                            @elseif($sale->delivery)
                                                <strong>{{ $sale->delivery->delivery_number }}</strong>
                                                <div class="text-muted small">Domicilio</div>
                                            @else
                                                <span class="text-muted">Sin pedido asociado</span>
                                            @endif
                                        </td>
                                        <td>
                                            <strong>{{ $sale->customer?->name ?: $sale->customer_name ?: 'Consumidor final' }}</strong>
                                            @if($sale->tableOrder)
                                                <div class="text-muted small">{{ $sale->tableOrder->order_number }} | {{ $sale->tableOrder->table?->name ?? 'Mesa' }}</div>
                                            @elseif($sale->delivery)
                                                <div class="text-muted small">{{ $sale->delivery->delivery_number }} | {{ $sale->delivery->delivery_address }}</div>
                                            @elseif($sale->notes)
                                                <div class="text-muted small">{{ $sale->notes }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <strong>{{ $sale->voidedBy?->name ?? 'Administrador' }}</strong>
                                            <div class="text-muted small">{{ $sale->voided_at?->format('d/m/Y H:i') }}</div>
                                        </td>
                                        <td>{{ $sale->void_reason ?: 'Sin motivo registrado' }}</td>
                                        <td><strong>${{ money($sale->total) }}</strong></td>
                                        <td class="text-end">
                                            <a href="{{ route('pos.sales.print', ['sale' => $sale, 'return_to' => request()->getRequestUri()]) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-print"></i> Imprimir
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        {{ $sales->links('pagination::bootstrap-5') }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
