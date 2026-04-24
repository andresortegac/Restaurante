@extends('layouts.app')

@section('title', 'Cierre Mensual de Caja - RestaurantePOS')

@section('content')
    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">POS / Gestion de Caja</span>
                <h1>Cierre mensual consolidado</h1>
                <p>Resumen financiero del mes con base inicial, ingresos, egresos y balance final consolidado.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">${{ number_format($summary['opening_base'], 2) }} base</span>
                <span class="summary-chip">${{ number_format($summary['income'], 2) }} ingresos</span>
                <span class="summary-chip">${{ number_format($summary['expense'], 2) }} egresos</span>
                <span class="summary-chip">${{ number_format($summary['balance'], 2) }} balance</span>
            </div>
        </section>

        <div class="module-toolbar">
            <form method="GET" action="{{ route('cash-management.monthly') }}" class="row g-2 align-items-end flex-grow-1">
                <div class="col-md-4">
                    <label class="form-label" for="month">Mes</label>
                    <input type="month" class="form-control" id="month" name="month" value="{{ $month }}">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary w-100">Generar</button>
                    <a href="{{ route('cash-management.monthly') }}" class="btn btn-outline-secondary w-100">Actual</a>
                </div>
            </form>
        </div>

        <div class="row g-4">
            <div class="col-xl-5">
                <div class="card module-card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-chart-pie"></i> Resumen por caja</h5>
                    </div>
                    <div class="card-body">
                        <div class="module-list">
                            @forelse($boxBreakdown as $boxName => $data)
                                <div class="module-list-item">
                                    <div>
                                        <strong>{{ $boxName }}</strong>
                                        <div class="table-note">Ingresos: ${{ number_format($data['income'], 2) }} | Egresos: ${{ number_format($data['expense'], 2) }}</div>
                                    </div>
                                    <span class="summary-chip">${{ number_format($data['income'] - $data['expense'], 2) }}</span>
                                </div>
                            @empty
                                <p class="text-muted mb-0">No hubo movimientos en el mes seleccionado.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-7">
                <div class="card module-card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-calendar-check"></i> Sesiones del mes</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Caja</th>
                                        <th>Responsable</th>
                                        <th>Apertura</th>
                                        <th>Cierre</th>
                                        <th>Diferencia</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($sessions as $session)
                                        <tr>
                                            <td>{{ $session->box?->name ?? 'Sin caja' }}</td>
                                            <td>{{ $session->user?->name ?? 'Sin responsable' }}</td>
                                            <td>{{ $session->opened_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                            <td>{{ $session->closed_at?->format('d/m/Y H:i') ?? 'Abierta' }}</td>
                                            <td>${{ number_format((float) ($session->difference_amount ?? 0), 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">No hay sesiones registradas en este mes.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
