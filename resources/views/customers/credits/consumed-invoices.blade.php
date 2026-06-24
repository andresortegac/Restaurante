@extends('layouts.app')

@section('title', 'Facturas consumidas de ' . $customer->name . ' - RestaurantePOS')

@section('content')
    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Clientes / Saldo a favor consumido</span>
                <h1>{{ $customer->name }}</h1>
                <p>Facturas y recibos donde se desconto saldo a favor del cliente.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">${{ money($consumedTotal) }} consumido</span>
                <span class="summary-chip">${{ money($summary['available']) }} disponible</span>
                <a href="{{ route('customers.credits.show', $customer) }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>
        </section>

        <div class="card module-card">
            <div class="card-header d-flex justify-content-between align-items-center gap-3">
                <h5 class="card-title mb-0"><i class="fas fa-file-invoice-dollar"></i> Consumos con saldo a favor</h5>
                <a href="{{ route('customers.credits.balance-history', $customer) }}" class="btn btn-outline-primary btn-sm">Historial completo</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Venta</th>
                                <th>Documento</th>
                                <th>Detalle</th>
                                <th class="text-end">Consumido</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($movements as $movement)
                                @php
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
                                        <strong>Venta #{{ $sale?->id }}</strong>
                                        <div class="table-note">
                                            @if($order?->order_number)
                                                {{ $order->order_number }}{{ $order->table?->name ? ' | ' . $order->table->name : '' }}
                                            @else
                                                Cobro manual
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        @if($invoice)
                                            <strong>{{ $invoice->invoice_number }}</strong>
                                            <div class="table-note">{{ $invoice->isElectronic() ? 'Factura electronica' : 'Ticket' }}</div>
                                        @else
                                            <span class="text-muted">Sin documento</span>
                                        @endif
                                    </td>
                                    <td>{{ $movement->description ?: 'Consumo de saldo a favor' }}</td>
                                    <td class="text-end">
                                        <strong>${{ money(abs((float) $movement->amount)) }}</strong>
                                        <div class="table-note">Saldo final: ${{ money($movement->balance_after) }}</div>
                                    </td>
                                    <td class="text-end">
                                        @if($sale)
                                            <a href="{{ route('pos.sales.print', $sale) }}" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener">
                                                Imprimir factura
                                            </a>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">Este cliente todavia no ha consumido saldo a favor.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3">
            {{ $movements->links() }}
        </div>
    </div>
@endsection
