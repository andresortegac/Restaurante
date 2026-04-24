@extends('layouts.app')

@section('title', 'Reportes de Ventas - RestaurantePOS')

@section('content')
    @php
        $invoiceLabels = [
            'validated' => 'Validada',
            'submitted' => 'Enviada',
            'pending' => 'Pendiente',
            'issued' => 'Emitida',
            'failed' => 'Fallida',
            'rejected' => 'Rechazada',
        ];
        $invoiceClasses = [
            'validated' => 'text-bg-success',
            'submitted' => 'text-bg-primary',
            'pending' => 'text-bg-warning',
            'issued' => 'text-bg-info',
            'failed' => 'text-bg-danger',
            'rejected' => 'text-bg-secondary',
        ];
    @endphp

    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Reportes / Ventas y operación</span>
                <h1>Resumen operativo del negocio</h1>
                <p>Consulta ventas, caja, domicilios y estado de facturación electrónica desde una sola vista con filtros por fecha, vendedor y método de pago.</p>
            </div>
            <div class="summary-group">
                <a href="{{ route('reports.analytics', request()->query()) }}" class="btn btn-outline-primary">
                    <i class="fas fa-chart-line"></i> Ver análisis
                </a>
                @if(auth()->user()->hasRole('Admin') || auth()->user()->hasPermission('reports.export'))
                    <a href="{{ route('reports.export', request()->query()) }}" class="btn btn-primary">
                        <i class="fas fa-file-csv"></i> Exportar CSV
                    </a>
                @endif
            </div>
        </section>

        <div class="card module-card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('reports.index') }}">
                    <div class="row g-3">
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label" for="date_from">Desde</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label" for="date_to">Hasta</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                        </div>
                        <div class="col-md-6 col-lg-2">
                            <label class="form-label" for="user_id">Vendedor</label>
                            <select class="form-select" id="user_id" name="user_id">
                                <option value="">Todos</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $user->id)>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 col-lg-2">
                            <label class="form-label" for="payment_method_id">Pago</label>
                            <select class="form-select" id="payment_method_id" name="payment_method_id">
                                <option value="">Todos</option>
                                @foreach($paymentMethods as $paymentMethod)
                                    <option value="{{ $paymentMethod->id }}" @selected((string) ($filters['payment_method_id'] ?? '') === (string) $paymentMethod->id)>{{ $paymentMethod->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 col-lg-2">
                            <label class="form-label" for="invoice_status">Factura</label>
                            <select class="form-select" id="invoice_status" name="invoice_status">
                                <option value="">Todas</option>
                                @foreach($invoiceStatuses as $invoiceStatus)
                                    <option value="{{ $invoiceStatus }}" @selected(($filters['invoice_status'] ?? '') === $invoiceStatus)>{{ $invoiceLabels[$invoiceStatus] ?? ucfirst($invoiceStatus) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Aplicar filtros
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="order-summary-card h-100">
                    <div class="summary-kicker">Ventas del periodo</div>
                    <div class="summary-value">{{ number_format($summary['sales_count']) }}</div>
                    <div class="table-note">Ticket promedio ${{ number_format($summary['average_ticket'], 2) }}</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="order-summary-card h-100">
                    <div class="summary-kicker">Ingreso neto</div>
                    <div class="summary-value">${{ number_format($summary['net_revenue'], 2) }}</div>
                    <div class="table-note">Impuestos ${{ number_format($summary['taxes'], 2) }}</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="order-summary-card h-100">
                    <div class="summary-kicker">Caja del periodo</div>
                    <div class="summary-value">${{ number_format($summary['cash_income'] - $summary['cash_expense'], 2) }}</div>
                    <div class="table-note">Ingresos ${{ number_format($summary['cash_income'], 2) }} | Egresos ${{ number_format($summary['cash_expense'], 2) }}</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="order-summary-card h-100">
                    <div class="summary-kicker">Facturación electrónica</div>
                    <div class="summary-value">{{ number_format($summary['electronic_invoices']) }}</div>
                    <div class="table-note">{{ $summary['invoice_success'] }} OK | {{ $summary['invoice_pending'] }} pendientes | {{ $summary['invoice_failed'] }} fallidas</div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-xl-8">
                <div class="card module-card h-100">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div>
                            <h5 class="mb-1">Ventas registradas</h5>
                            <p class="table-note mb-0">Periodo del {{ $dateRange['start']->format('d/m/Y') }} al {{ $dateRange['end']->format('d/m/Y') }}.</p>
                        </div>
                        <span class="summary-chip">{{ number_format($sales->total()) }} registros</span>
                    </div>
                    <div class="card-body">
                        @if($sales->isEmpty())
                            <div class="empty-state">
                                <i class="fas fa-chart-column"></i>
                                <h5 class="mb-2">No hay ventas para este filtro</h5>
                                <p class="mb-0">Prueba ampliando el rango de fechas o quitando alguno de los filtros aplicados.</p>
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Venta</th>
                                            <th>Fecha</th>
                                            <th>Cliente</th>
                                            <th>Vendedor</th>
                                            <th>Pago</th>
                                            <th>Total</th>
                                            <th>Factura</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($sales as $sale)
                                            @php
                                                $invoiceStatus = $sale->invoice?->status;
                                            @endphp
                                            <tr>
                                                <td>
                                                    <strong>#{{ $sale->id }}</strong>
                                                    @if($sale->tableOrder)
                                                        <div class="table-note">{{ $sale->tableOrder->order_number }}{{ $sale->tableOrder->table ? ' | Mesa '.$sale->tableOrder->table->name : '' }}</div>
                                                    @endif
                                                </td>
                                                <td>
                                                    {{ $sale->created_at?->format('d/m/Y H:i') }}
                                                    <div class="table-note">Estado {{ ucfirst($sale->status) }}</div>
                                                </td>
                                                <td>{{ $sale->customer?->name ?? $sale->customer_name ?? 'Sin cliente' }}</td>
                                                <td>{{ $sale->user?->name ?? 'Sin usuario' }}</td>
                                                <td>{{ $sale->payments->pluck('paymentMethod.name')->filter()->implode(', ') ?: 'Sin pago' }}</td>
                                                <td>
                                                    <strong>${{ number_format((float) $sale->total, 2) }}</strong>
                                                    <div class="table-note">Desc. ${{ number_format((float) $sale->discount_amount, 2) }}</div>
                                                </td>
                                                <td>
                                                    @if($sale->invoice)
                                                        <span class="badge rounded-pill {{ $invoiceClasses[$invoiceStatus] ?? 'text-bg-secondary' }}">
                                                            {{ $invoiceLabels[$invoiceStatus] ?? ucfirst((string) $invoiceStatus) }}
                                                        </span>
                                                        <div class="table-note">{{ $sale->invoice->invoice_number }}</div>
                                                    @else
                                                        <span class="badge rounded-pill text-bg-light">Sin factura</span>
                                                    @endif
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

            <div class="col-xl-4">
                <div class="card module-card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-file-invoice-dollar"></i> Estado facturas</h5>
                    </div>
                    <div class="card-body">
                        <div class="module-list">
                            @forelse($statusBreakdown['invoices'] as $status => $total)
                                <div class="module-list-item">
                                    <span>{{ $invoiceLabels[$status] ?? ucfirst((string) $status) }}</span>
                                    <span class="summary-chip">{{ number_format($total) }}</span>
                                </div>
                            @empty
                                <p class="text-muted mb-0">No hay facturas electrónicas en el periodo seleccionado.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="card module-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-motorcycle"></i> Estado domicilios</h5>
                    </div>
                    <div class="card-body">
                        <div class="module-list">
                            @forelse($statusBreakdown['deliveries'] as $status => $total)
                                <div class="module-list-item">
                                    <span>{{ ucfirst(str_replace('_', ' ', (string) $status)) }}</span>
                                    <span class="summary-chip">{{ number_format($total) }}</span>
                                </div>
                            @empty
                                <p class="text-muted mb-0">No hay domicilios para este rango.</p>
                            @endforelse
                        </div>

                        <hr>

                        <div class="table-note">Clientes atendidos: {{ number_format($summary['customers_served']) }}</div>
                        <div class="table-note">Domicilios completados: {{ number_format($summary['deliveries_completed']) }}</div>
                        <div class="table-note">Domicilios en curso: {{ number_format($summary['deliveries_active']) }}</div>
                        <div class="table-note">Ventas brutas: ${{ number_format($summary['gross_sales'], 2) }}</div>
                        <div class="table-note">Descuentos: ${{ number_format($summary['discounts'], 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
