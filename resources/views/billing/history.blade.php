@extends('layouts.app')

@section('title', 'Historial de Cobros - Facturación')

@section('content')
    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Caja / Facturación</span>
                <h1>Historial de cobros</h1>
                <p>Consulta los cobros cerrados de mesas, revisa si salieron como ticket o factura electrónica y vuelve a imprimir el documento cuando haga falta.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">{{ number_format($summary['sales']) }} cobros</span>
                <span class="summary-chip">{{ number_format($summary['today']) }} hoy</span>
                <span class="summary-chip">${{ number_format($summary['revenue'], 2) }} vendidos</span>
                <span class="summary-chip">{{ number_format($summary['electronic']) }} electrónicas</span>
            </div>
        </section>

        <div class="card module-card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('billing.history') }}">
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <label class="form-label" for="search">Buscar</label>
                            <input type="text" class="form-control" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Mesa, pedido, cliente, documento o CUFE">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="document_type">Documento</label>
                            <select class="form-select" id="document_type" name="document_type">
                                <option value="">Todos</option>
                                <option value="ticket" @selected(($filters['document_type'] ?? '') === 'ticket')>Ticket</option>
                                <option value="electronic" @selected(($filters['document_type'] ?? '') === 'electronic')>Factura electrónica</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="status">Estado</label>
                            <input type="text" class="form-control" id="status" name="status" value="{{ $filters['status'] ?? '' }}" placeholder="issued, validated, failed...">
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="{{ route('billing.history') }}" class="btn btn-outline-secondary">Limpiar</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filtrar historial
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card module-card">
            <div class="card-body">
                @if($sales->isEmpty())
                    <div class="empty-state">
                        <i class="fas fa-clock-rotate-left"></i>
                        <h3>No hay cobros registrados</h3>
                        <p>Cuando se cobren mesas desde facturación, aparecerán aquí.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Venta</th>
                                    <th>Fecha</th>
                                    <th>Origen</th>
                                    <th>Cajero</th>
                                    <th>Documento</th>
                                    <th>Cobro</th>
                                    <th>Total</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($sales as $sale)
                                    @php
                                        $primaryPayment = $sale->payments->first();
                                        $invoice = $sale->invoice;
                                    @endphp
                                    <tr>
                                        <td>
                                            <strong>#{{ $sale->id }}</strong>
                                            <div class="text-muted small">{{ $sale->box?->name ?? 'Sin caja' }}</div>
                                        </td>
                                        <td>{{ $sale->created_at?->format('d/m/Y H:i') }}</td>
                                        <td>
                                            <strong>{{ $sale->tableOrder?->order_number ?? 'Sin pedido' }}</strong>
                                            <div class="text-muted small">{{ $sale->tableOrder?->table?->name ?? 'Mesa sin referencia' }}</div>
                                            <div class="text-muted small">{{ $sale->customer?->name ?: $sale->customer_name ?: 'Consumidor final' }}</div>
                                        </td>
                                        <td>{{ $sale->user?->name ?? 'Sin usuario' }}</td>
                                        <td>
                                            @if($invoice)
                                                <strong>{{ $invoice->isElectronic() ? 'Factura electrónica' : 'Ticket' }}</strong>
                                                <div class="text-muted small">{{ $invoice->invoice_number }}</div>
                                                <div class="text-muted small">Estado: {{ $invoice->status }}</div>
                                                @if($invoice->cufe)
                                                    <div class="text-muted small">CUFE: {{ $invoice->cufe }}</div>
                                                @endif
                                            @else
                                                <span class="text-muted">Sin documento</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($primaryPayment)
                                                <strong>{{ $sale->payments->pluck('paymentMethod.name')->filter()->join(', ') ?: 'Sin pago' }}</strong>
                                                <div class="text-muted small">Recibido: ${{ number_format((float) $primaryPayment->received_amount, 2) }}</div>
                                                <div class="text-muted small">Cambio: ${{ number_format((float) $primaryPayment->change_amount, 2) }}</div>
                                                <div class="text-muted small">Propina: ${{ number_format((float) $primaryPayment->tip_amount, 2) }}</div>
                                            @else
                                                <span class="text-muted">Sin pago</span>
                                            @endif
                                        </td>
                                        <td>
                                            <strong>${{ number_format((float) $sale->total, 2) }}</strong>
                                            @if($primaryPayment && (float) $primaryPayment->tip_amount > 0)
                                                <div class="text-muted small">Con propina: ${{ number_format((float) $sale->total + (float) $primaryPayment->tip_amount, 2) }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="table-actions">
                                                <a href="{{ route('pos.sales.print', $sale) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-print"></i> Imprimir
                                                </a>
                                                @if($invoice && $invoice->isElectronic())
                                                    <a href="{{ route('electronic-invoices.show', $invoice) }}" class="btn btn-sm btn-outline-secondary">
                                                        Ver FE
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
                        {{ $sales->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
