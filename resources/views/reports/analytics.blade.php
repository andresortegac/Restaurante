@extends('layouts.app')

@section('title', 'Análisis de Reportes - RestaurantePOS')

@section('content')
    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Reportes / Análisis</span>
                <h1>Tendencias y rendimiento</h1>
                <p>Compara el desempeño por vendedor, método de pago, productos, domicilios y facturación dentro del periodo seleccionado.</p>
            </div>
            <div class="summary-group">
                <a href="{{ route('reports.index', request()->query()) }}" class="btn btn-outline-primary">
                    <i class="fas fa-table"></i> Volver al resumen
                </a>
            </div>
        </section>

        <div class="card module-card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('reports.analytics') }}">
                    <div class="row g-3">
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label" for="date_from">Desde</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label" for="date_to">Hasta</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label" for="user_id">Vendedor</label>
                            <select class="form-select" id="user_id" name="user_id">
                                <option value="">Todos</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $user->id)>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label" for="payment_method_id">Pago</label>
                            <select class="form-select" id="payment_method_id" name="payment_method_id">
                                <option value="">Todos</option>
                                @foreach($paymentMethods as $paymentMethod)
                                    <option value="{{ $paymentMethod->id }}" @selected((string) ($filters['payment_method_id'] ?? '') === (string) $paymentMethod->id)>{{ $paymentMethod->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="{{ route('reports.analytics') }}" class="btn btn-outline-secondary">Limpiar</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Actualizar análisis
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-xl-6">
                <div class="card module-card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Ventas por vendedor</h5>
                    </div>
                    <div class="card-body">
                        <div class="module-list">
                            @forelse($salesByUser as $row)
                                <div class="module-list-item">
                                    <div>
                                        <strong>{{ $row->user?->name ?? 'Sin usuario' }}</strong>
                                        <div class="table-note">{{ number_format((int) $row->sales_count) }} ventas</div>
                                    </div>
                                    <span class="summary-chip">${{ number_format((float) $row->revenue, 2) }}</span>
                                </div>
                            @empty
                                <p class="text-muted mb-0">No hay ventas registradas en este rango.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card module-card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Métodos de pago</h5>
                    </div>
                    <div class="card-body">
                        <div class="module-list">
                            @forelse($paymentBreakdown as $row)
                                <div class="module-list-item">
                                    <div>
                                        <strong>{{ $row->paymentMethod?->name ?? 'Sin método' }}</strong>
                                        <div class="table-note">{{ number_format((int) $row->payments_count) }} pagos</div>
                                    </div>
                                    <span class="summary-chip">${{ number_format((float) $row->total_amount, 2) }}</span>
                                </div>
                            @empty
                                <p class="text-muted mb-0">No hay pagos para mostrar.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card module-card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Tendencia diaria</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Ventas</th>
                                        <th>Ingresos</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($dailySales as $row)
                                        <tr>
                                            <td>{{ \Carbon\Carbon::parse($row->report_date)->format('d/m/Y') }}</td>
                                            <td>{{ number_format((int) $row->sales_count) }}</td>
                                            <td>${{ number_format((float) $row->revenue, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center text-muted">Sin movimientos en el periodo.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card module-card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Productos más vendidos</h5>
                    </div>
                    <div class="card-body">
                        <div class="module-list">
                            @forelse($topProducts as $row)
                                <div class="module-list-item">
                                    <div>
                                        <strong>{{ $row->product_name }}</strong>
                                        <div class="table-note">{{ number_format((int) $row->quantity_sold) }} unidades</div>
                                    </div>
                                    <span class="summary-chip">${{ number_format((float) $row->revenue, 2) }}</span>
                                </div>
                            @empty
                                <p class="text-muted mb-0">Todavía no hay productos acumulados en este rango.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card module-card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Facturación electrónica</h5>
                    </div>
                    <div class="card-body">
                        <div class="module-list">
                            @forelse($invoiceBreakdown as $row)
                                <div class="module-list-item">
                                    <span>{{ ucfirst((string) $row->status) }}</span>
                                    <span class="summary-chip">{{ number_format((int) $row->total) }}</span>
                                </div>
                            @empty
                                <p class="text-muted mb-0">No hay facturas electrónicas para este rango.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card module-card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Domicilios</h5>
                    </div>
                    <div class="card-body">
                        <div class="module-list">
                            @forelse($deliveryBreakdown as $row)
                                <div class="module-list-item">
                                    <div>
                                        <strong>{{ ucfirst(str_replace('_', ' ', (string) $row->status)) }}</strong>
                                        <div class="table-note">{{ number_format((int) $row->total) }} registros</div>
                                    </div>
                                    <span class="summary-chip">${{ number_format((float) $row->billed_total, 2) }}</span>
                                </div>
                            @empty
                                <p class="text-muted mb-0">No hay domicilios para este periodo.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
