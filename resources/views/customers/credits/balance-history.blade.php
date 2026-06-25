@extends('layouts.app')

@section('title', 'Historial de saldo a favor de ' . $customer->name . ' - RestaurantePOS')

@section('content')
    @php
        $movementLabels = [
            'manual_addition' => 'Ingreso manual',
            'manual_removal' => 'Descuento manual',
            'sale_consumption' => 'Consumo en venta',
            'customer_payment' => 'Pago del cliente',
        ];
    @endphp

    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Clientes / Historial de saldo a favor</span>
                <h1>{{ $customer->name }}</h1>
                <p>Consulta entradas, ajustes y consumos de saldo a favor con su venta, recibo o factura cuando aplique.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">${{ money($summary['available']) }} disponible</span>
            </div>
        </section>

        <div class="card module-card service-card">
            <div class="card-header d-flex justify-content-between align-items-center gap-3">
                <div>
                    <h5 class="mb-1">Historial del saldo a favor</h5>
                    <p class="table-note mb-0">Los consumos hechos en pedidos de mesa o cobros manuales incluyen acceso directo para imprimir el documento.</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="{{ route('customers.credits.show', $customer) }}" class="btn btn-primary btn-sm">Volver a gestionar</a>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('customers.credits.balance-history', $customer) }}" class="row g-2 align-items-end mb-4">
                    <div class="col-md-3">
                        <label class="form-label" for="date_from">Desde</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="date_to">Hasta</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="ticket">Ticket o factura</label>
                        <input type="text" class="form-control" id="ticket" name="ticket" value="{{ $filters['ticket'] ?? '' }}" placeholder="Numero de ticket, factura o venta">
                    </div>
                    <div class="col-md-3">
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" id="printable" name="printable" value="1" @checked((bool) ($filters['printable'] ?? false))>
                            <label class="form-check-label" for="printable">Solo con recibo para imprimir</label>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-outline-primary w-100">Filtrar</button>
                            <a href="{{ route('customers.credits.balance-history', $customer) }}" class="btn btn-outline-secondary w-100">Limpiar</a>
                        </div>
                    </div>
                </form>

                @if($movements->isEmpty())
                    <div class="empty-state py-4">
                        <i class="fas fa-wallet"></i>
                        <h5 class="mb-2">Sin movimientos de saldo a favor</h5>
                        <p class="mb-0">No hay movimientos que coincidan con los filtros aplicados.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Movimiento</th>
                                    <th>Concepto</th>
                                    <th>Pedido o venta</th>
                                    <th>Documento</th>
                                    <th>Monto</th>
                                    <th>Saldo resultante</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($movements as $movement)
                                    @php
                                        $amount = (float) $movement->amount;
                                        $isPositive = $amount >= 0;
                                        $sale = $movement->sale;
                                        $invoice = $sale?->invoice;
                                        $order = $sale?->tableOrder;
                                    @endphp
                                    <tr>
                                        <td>
                                            <strong>{{ $movement->created_at?->format('d/m/Y') }}</strong>
                                            <div class="table-note">{{ $movement->created_at?->format('H:i') }}</div>
                                        </td>
                                        <td>
                                            <strong>{{ $movementLabels[$movement->movement_type] ?? 'Saldo a favor' }}</strong>
                                            <div class="table-note">
                                                @if($movement->sale?->tableOrder?->order_number)
                                                    Venta #{{ $movement->sale_id }} | {{ $movement->sale->tableOrder->order_number }}
                                                @elseif($movement->sale_id)
                                                    Venta #{{ $movement->sale_id }}
                                                @elseif($movement->createdBy?->name)
                                                    Registrado por {{ $movement->createdBy->name }}
                                                @else
                                                    Registro manual
                                                @endif
                                            </div>
                                        </td>
                                        <td>{{ $movement->description }}</td>
                                        <td>
                                            @if($sale)
                                                <strong>Venta #{{ $sale->id }}</strong>
                                                <div class="table-note">
                                                    @if($order?->order_number)
                                                        {{ $order->order_number }}{{ $order->table?->name ? ' | ' . $order->table->name : '' }}
                                                    @elseif(str_contains((string) $sale->notes, 'Pedido de mesa manual'))
                                                        Cobro manual en mesa
                                                    @else
                                                        Cobro manual
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-muted">Movimiento manual</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($invoice)
                                                <strong>{{ $invoice->invoice_number }}</strong>
                                                <div class="table-note">{{ $invoice->isElectronic() ? 'Factura electronica' : 'Recibo/Ticket' }}</div>
                                            @elseif($sale)
                                                <strong>Recibo pendiente</strong>
                                                <div class="table-note">Se generara al imprimir</div>
                                            @else
                                                <span class="text-muted">No aplica</span>
                                            @endif
                                        </td>
                                        <td class="{{ $isPositive ? 'text-success' : 'text-danger' }}">
                                            <strong>{{ $isPositive ? '+' : '-' }}${{ money(abs($amount)) }}</strong>
                                            <div class="table-note">Antes: ${{ money($movement->balance_before) }}</div>
                                        </td>
                                        <td>
                                            <strong>${{ money($movement->balance_after) }}</strong>
                                            <div class="table-note">Disponible despues del movimiento</div>
                                        </td>
                                        <td class="text-end">
                                            @if($sale)
                                                <a href="{{ route('pos.sales.print', $sale) }}" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener">
                                                    Imprimir
                                                </a>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        {{ $movements->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
