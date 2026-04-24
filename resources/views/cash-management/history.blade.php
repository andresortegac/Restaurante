@extends('layouts.app')

@section('title', 'Historial de Caja - RestaurantePOS')

@section('content')
    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">POS / Gestion de Caja</span>
                <h1>Historial y auditoria</h1>
                <p>Consulta aperturas, cierres y movimientos de caja con filtros por fecha, usuario y tipo de accion.</p>
            </div>
        </section>

        <div class="module-toolbar">
            <form method="GET" action="{{ route('cash-management.history') }}" class="row g-2 align-items-end flex-grow-1">
                <div class="col-md-3">
                    <label class="form-label" for="date_from">Desde</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="date_to">Hasta</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="user_id">Usuario</label>
                    <select class="form-select" id="user_id" name="user_id">
                        <option value="">Todos</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="action">Accion</label>
                    <select class="form-select" id="action" name="action">
                        <option value="">Todas</option>
                        @foreach($actions as $action)
                            <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary">Filtrar</button>
                    <a href="{{ route('cash-management.history') }}" class="btn btn-outline-secondary">Limpiar</a>
                </div>
            </form>
        </div>

        <div class="card module-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Accion</th>
                                <th>Caja</th>
                                <th>Usuario</th>
                                <th>Detalle</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                                <tr>
                                    <td>{{ $log->occurred_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                    <td>{{ str_replace('_', ' ', $log->action) }}</td>
                                    <td>{{ $log->box?->name ?? 'Sin caja' }}</td>
                                    <td>{{ $log->user?->name ?? 'Sistema' }}</td>
                                    <td>
                                        <strong>{{ $log->description ?: 'Sin detalle' }}</strong>
                                        @if(!empty($log->metadata))
                                            <div class="table-note">{{ json_encode($log->metadata, JSON_UNESCAPED_UNICODE) }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No hay registros para los filtros seleccionados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3">
            {{ $logs->links() }}
        </div>
    </div>
@endsection
