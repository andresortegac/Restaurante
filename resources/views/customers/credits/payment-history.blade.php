@extends('layouts.app')

@section('title', 'Historial de pagos de ' . $customer->name . ' - RestaurantePOS')

@section('content')
    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Clientes / Historial de pago</span>
                <h1>{{ $customer->name }}</h1>
                <p>Consulta los abonos registrados y reimprime los recibos de pago del cliente.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">${{ money($summary['pending']) }} pendiente</span>
                <span class="summary-chip">{{ $receipts->total() }} recibos</span>
            </div>
        </section>

        <div class="card module-card service-card">
            <div class="card-header d-flex justify-content-between align-items-center gap-3">
                <div>
                    <h5 class="mb-1">Recibos de pago</h5>
                    <p class="table-note mb-0">Cada recibo corresponde a un cobro o abono registrado en cartera.</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    @if($summary['pending'] > 0)
                        <a href="{{ route('customers.credits.collect', $customer) }}" class="btn btn-success btn-sm">Cobrar deuda</a>
                    @endif
                    <a href="{{ route('customers.credits.show', $customer) }}" class="btn btn-outline-secondary btn-sm">Volver</a>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('customers.credits.payments.history', $customer) }}" class="row g-2 align-items-end mb-4">
                    <div class="col-md-3">
                        <label class="form-label" for="date_from">Desde</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="date_to">Hasta</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="invoice">Factura o recibo</label>
                        <input type="text" class="form-control" id="invoice" name="invoice" value="{{ $filters['invoice'] ?? '' }}" placeholder="Factura, ticket, recibo o nota">
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-outline-primary w-100">Filtrar</button>
                        <a href="{{ route('customers.credits.payments.history', $customer) }}" class="btn btn-outline-secondary w-100">Limpiar</a>
                    </div>
                </form>

                @if($receipts->isEmpty())
                    <div class="empty-state py-4">
                        <i class="fas fa-receipt"></i>
                        <h5 class="mb-2">Sin recibos de pago</h5>
                        <p class="mb-0">No hay recibos que coincidan con los filtros aplicados.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Recibo</th>
                                    <th>Fecha</th>
                                    <th>Metodo</th>
                                    <th>Caja</th>
                                    <th>Valor</th>
                                    <th>Pendiente despues</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($receipts as $receipt)
                                    <tr>
                                        <td>
                                            <strong>{{ $receipt->receipt_number }}</strong>
                                            <div class="table-note">{{ $receipt->reference ?: 'Sin referencia' }}</div>
                                        </td>
                                        <td>
                                            <strong>{{ $receipt->paid_at?->format('d/m/Y') }}</strong>
                                            <div class="table-note">{{ $receipt->paid_at?->format('H:i') }}</div>
                                        </td>
                                        <td>{{ $receipt->paymentMethod?->name ?: 'Efectivo' }}</td>
                                        <td>
                                            <strong>{{ $receipt->box?->name ?: 'Caja no disponible' }}</strong>
                                            <div class="table-note">Impacto caja: ${{ money($receipt->box_impact) }}</div>
                                        </td>
                                        <td><strong>${{ money($receipt->amount) }}</strong></td>
                                        <td>${{ money($receipt->remaining_pending) }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('customers.credits.receipts.print', [$customer, $receipt]) }}" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener">Imprimir</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        {{ $receipts->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
