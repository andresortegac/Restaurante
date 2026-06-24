@extends('layouts.app')

@section('title', 'Cierre de caja - ' . config('app.name', 'Solomo & Pomo'))

@section('content')
    @php
        $movementLabels = [
            'sale_income' => 'Venta POS',
            'table_order_payment' => 'Consumo de mesa',
            'manual_payment' => 'Cobro manual',
            'delivery_payment' => 'Domicilio',
            'credit_payment' => 'Pago de credito',
            'customer_credit_payment' => 'Pago de cartera',
            'manual_income' => 'Ingreso manual',
        ];
    @endphp

    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">POS / Gestion de Caja</span>
                <h1>Cierre de caja</h1>
                <p>{{ $session->box?->name ?? 'Caja' }} | {{ $session->closed_at?->format('d/m/Y H:i') ?? 'Cierre sin fecha' }}</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">{{ number_format($summary['income_movements']) }} ingresos</span>
                <span class="summary-chip">${{ money($summary['income_total']) }} caja fisica</span>
                <span class="summary-chip">${{ money($summary['reported_payment_total']) }} informado</span>
                <span class="summary-chip">${{ money($summary['sales_income']) }} consumo</span>
                <span class="summary-chip">${{ money($summary['manual_income']) }} manual</span>
                <a href="{{ route('cash-management.history.sessions.print', $session) }}" class="btn btn-outline-dark" target="_blank" rel="noopener">
                    <i class="fas fa-print"></i> Imprimir detallado
                </a>
                <a href="{{ route('cash-management.history') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>
        </section>

        <div class="card module-card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="meta-box h-100">
                            <div class="summary-kicker">Responsable</div>
                            <div class="fw-bold">{{ $session->user?->name ?? 'Sin responsable' }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="meta-box h-100">
                            <div class="summary-kicker">Base inicial</div>
                            <div class="fw-bold">${{ money($session->opening_balance) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="meta-box h-100">
                            <div class="summary-kicker">Valor contado</div>
                            <div class="fw-bold">${{ money($session->counted_balance) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="meta-box h-100">
                            <div class="summary-kicker">Diferencia</div>
                            <div class="fw-bold">${{ money($session->difference_amount) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($paymentBreakdown->isNotEmpty())
            <div class="card module-card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-credit-card"></i> Entradas por metodo de pago</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Metodo</th>
                                    <th class="text-end">Operaciones</th>
                                    <th class="text-end">Total informado</th>
                                    <th class="text-end">Impacto en caja</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($paymentBreakdown as $paymentRow)
                                    <tr>
                                        <td>
                                            <strong>{{ $paymentRow['name'] }}</strong>
                                            <div class="table-note">{{ $paymentRow['affects_box'] ? 'Afecta caja fisica' : 'Solo informativo' }}</div>
                                        </td>
                                        <td class="text-end">{{ number_format($paymentRow['count']) }}</td>
                                        <td class="text-end">${{ money($paymentRow['total']) }}</td>
                                        <td class="text-end">${{ money($paymentRow['box_impact']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <div class="card module-card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('cash-management.history.sessions.show', $session) }}">
                    <div class="row g-3 align-items-end">
                        <div class="col-lg-9">
                            <label class="form-label" for="search">Buscar movimiento</label>
                            <input type="text" class="form-control" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Numero de factura, venta, cliente, CUFE o descripcion">
                        </div>
                        <div class="col-lg-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Buscar
                            </button>
                            <a href="{{ route('cash-management.history.sessions.show', $session) }}" class="btn btn-outline-secondary">Limpiar</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card module-card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-money-bill-wave"></i> Ingresos del cierre</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Hora</th>
                                <th>Movimiento</th>
                                <th>Factura / venta</th>
                                <th>Cliente</th>
                                <th>Detalle</th>
                                <th class="text-end">Ingreso</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($movements as $movement)
                                @php
                                    $sale = $movement->sale;
                                    $invoice = $sale?->invoice;
                                    $customerName = $sale?->customer?->name ?: $sale?->customer_name;
                                @endphp
                                <tr>
                                    <td>{{ $movement->occurred_at?->format('H:i') ?? '-' }}</td>
                                    <td>
                                        <strong>{{ $movementLabels[$movement->movement_type] ?? str_replace('_', ' ', $movement->movement_type) }}</strong>
                                        <div class="table-note">{{ $movement->user?->name ?? 'Sistema' }}</div>
                                    </td>
                                    <td>
                                        @if($sale)
                                            <strong>Venta #{{ $sale->id }}</strong>
                                            @if($invoice)
                                                <div class="table-note">{{ $invoice->invoice_number }}</div>
                                                <div class="table-note">{{ $invoice->isElectronic() ? 'Factura electronica' : 'Ticket' }}</div>
                                            @else
                                                <div class="table-note">Sin documento</div>
                                            @endif
                                        @else
                                            <span class="text-muted">Sin venta asociada</span>
                                        @endif
                                    </td>
                                    <td>{{ $customerName ?: 'Consumidor final' }}</td>
                                    <td>
                                        {{ $movement->description ?: 'Sin detalle' }}
                                        @if($sale?->tableOrder)
                                            <div class="table-note">{{ $sale->tableOrder->order_number }} | {{ $sale->tableOrder->table?->name ?? 'Mesa' }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <strong>${{ money($movement->amount) }}</strong>
                                    </td>
                                    <td class="text-end">
                                        <div class="table-actions justify-content-end">
                                            @if($sale)
                                                <a href="{{ route('pos.sales.print', $sale) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-download"></i> Factura
                                                </a>
                                            @endif
                                            @if($invoice && $invoice->isElectronic() && $invoice->pdf_path)
                                                <a href="{{ route('electronic-invoices.pdf', $invoice) }}" class="btn btn-sm btn-outline-secondary">
                                                    PDF
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No hay ingresos para este cierre con los filtros seleccionados.</td>
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
