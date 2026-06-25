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
            'customer_balance_payment' => 'Pago de saldo del cliente',
            'manual_income' => 'Ingreso manual',
            'manual_expense' => 'Egreso manual',
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
                <a href="{{ route('cash-management.history') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>
        </section>

        <div class="card module-card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md">
                        <div class="meta-box h-100">
                            <div class="summary-kicker">Responsable</div>
                            <div class="fw-bold">{{ $session->user?->name ?? 'Sin responsable' }}</div>
                        </div>
                    </div>
                    <div class="col-md">
                        <div class="meta-box h-100">
                            <div class="summary-kicker">Base inicial</div>
                            <div class="fw-bold">${{ money($session->opening_balance) }}</div>
                        </div>
                    </div>
                    <div class="col-md">
                        <div class="meta-box h-100">
                            <div class="summary-kicker">Valor contado en efectivo</div>
                            <div class="fw-bold">${{ money($session->counted_balance) }}</div>
                        </div>
                    </div>
                    <div class="col-md">
                        <div class="meta-box h-100">
                            <div class="summary-kicker">Transferencias</div>
                            <div class="fw-bold">${{ money($summary['transfer_total']) }}</div>
                        </div>
                    </div>
                    <div class="col-md">
                        <div class="meta-box h-100">
                            <div class="summary-kicker">Diferencia de efectivo</div>
                            <div class="fw-bold">${{ money($session->difference_amount) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card module-card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-calculator"></i> Cuadre de caja</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Concepto</th>
                                <th class="text-end">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Base inicial</td>
                                <td class="text-end">${{ money($session->opening_balance) }}</td>
                            </tr>
                            <tr>
                                <td>Entradas en efectivo</td>
                                <td class="text-end">${{ money($summary['income_total']) }}</td>
                            </tr>
                            <tr>
                                <td>Salidas en efectivo</td>
                                <td class="text-end">-${{ money($summary['expense_total']) }}</td>
                            </tr>
                            <tr>
                                <td><strong>Saldo esperado en efectivo</strong></td>
                                <td class="text-end"><strong>${{ money($summary['expected_cash_total']) }}</strong></td>
                            </tr>
                            <tr>
                                <td>Valor contado en efectivo</td>
                                <td class="text-end">${{ money($session->counted_balance) }}</td>
                            </tr>
                            <tr>
                                <td>Diferencia de efectivo</td>
                                <td class="text-end">${{ money($session->difference_amount) }}</td>
                            </tr>
                            <tr>
                                <td>Transferencias informadas</td>
                                <td class="text-end">${{ money($summary['transfer_total']) }}</td>
                            </tr>
                        </tbody>
                    </table>
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
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($paymentBreakdown as $paymentRow)
                                    <tr>
                                        <td>
                                            <strong>{{ $paymentRow['name'] }}</strong>
                                        </td>
                                        <td class="text-end">{{ number_format($paymentRow['count']) }}</td>
                                        <td class="text-end">${{ money($paymentRow['total']) }}</td>
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
                        <div class="col-lg-5">
                            <label class="form-label" for="search">Buscar movimiento</label>
                            <input type="text" class="form-control" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Numero de factura, venta, cliente, CUFE o descripcion">
                        </div>
                        <div class="col-lg-2">
                            <label class="form-label" for="date_from">Desde</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                        </div>
                        <div class="col-lg-2">
                            <label class="form-label" for="date_to">Hasta</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
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
                <h5 class="card-title mb-0"><i class="fas fa-money-bill-wave"></i> Movimientos para imprimir</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Hora</th>
                                <th>Movimiento</th>
                                <th>Documento / metodo</th>
                                <th>Cliente</th>
                                <th>Detalle</th>
                                <th class="text-end">Valor</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($movements as $movement)
                                @php
                                    $sale = $movement->sale;
                                    $invoice = $sale?->invoice;
                                    $customerName = $sale?->customer?->name ?: $sale?->customer_name;
                                    $displayAmount = (float) ($movement->display_amount ?? $movement->amount);
                                    $isExpense = $displayAmount < 0;
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
                                            <strong>Tirilla #{{ $movement->id }}</strong>
                                        @endif
                                        <div class="table-note">{{ $movement->display_payment_method ?? 'Sin metodo' }}</div>
                                    </td>
                                    <td>{{ $customerName ?: 'Consumidor final' }}</td>
                                    <td>
                                        {{ $movement->description ?: 'Sin detalle' }}
                                        @if($sale?->tableOrder)
                                            <div class="table-note">{{ $sale->tableOrder->order_number }} | {{ $sale->tableOrder->table?->name ?? 'Mesa' }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <strong class="{{ $isExpense ? 'text-danger' : 'text-success' }}">{{ $isExpense ? '-' : '' }}${{ money(abs($displayAmount)) }}</strong>
                                    </td>
                                    <td class="text-end">
                                        <div class="table-actions justify-content-end">
                                            <a href="{{ route('cash-management.history.movements.print', $movement) }}" target="_blank" class="btn btn-sm btn-outline-dark">
                                                <i class="fas fa-print"></i> Tirilla
                                            </a>
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
                                    <td colspan="7" class="text-center py-4 text-muted">No hay movimientos para los filtros seleccionados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="5">Totales de la sesion</th>
                                <th class="text-end">
                                    <div>Efectivo neto: ${{ money($summary['cash_net_total']) }}</div>
                                    <div>Transferencias: ${{ money($summary['transfer_total']) }}</div>
                                </th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3">
            {{ $movements->links() }}
        </div>
    </div>
@endsection
