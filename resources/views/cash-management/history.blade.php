@extends('layouts.app')

@section('title', 'Historial de cierres - ' . config('app.name', 'Solomo & Pomo'))

@section('content')
    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">POS / Gestion de Caja</span>
                <h1>Historial de cierres</h1>
                <p>Consulta cada cierre de caja por turno y entra al detalle para revisar solo los movimientos de esa sesion.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">{{ number_format($summary['closures']) }} cierres</span>
                <span class="summary-chip">{{ number_format($summary['morning']) }} mañana</span>
                <span class="summary-chip">{{ number_format($summary['afternoon']) }} tarde</span>
                <span class="summary-chip">{{ number_format($summary['night']) }} noche</span>
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
                    <label class="form-label" for="box_id">Caja</label>
                    <select class="form-select" id="box_id" name="box_id">
                        <option value="">Todas</option>
                        @foreach($boxes as $box)
                            <option value="{{ $box->id }}" @selected((string) ($filters['box_id'] ?? '') === (string) $box->id)>{{ $box->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="user_id">Responsable</label>
                    <select class="form-select" id="user_id" name="user_id">
                        <option value="">Todos</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="fas fa-filter"></i> Filtrar cierres
                    </button>
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
                                <th>Cierre</th>
                                <th>Turno</th>
                                <th>Caja</th>
                                <th>Responsable</th>
                                <th>Resumen</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($sessions as $session)
                                @php
                                    $closedAt = $session->closed_at;
                                    $hour = (int) ($closedAt ?? $session->opened_at ?? now())->format('H');
                                    $turnLabel = $hour < 12 ? 'Cierre de la mañana' : ($hour < 18 ? 'Cierre de la tarde' : 'Cierre de la noche');
                                @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $closedAt?->format('d/m/Y H:i') ?? 'Sin fecha de cierre' }}</strong>
                                        <div class="table-note">Apertura: {{ $session->opened_at?->format('d/m/Y H:i') ?? '-' }}</div>
                                    </td>
                                    <td>{{ $turnLabel }}</td>
                                    <td>{{ $session->box?->name ?? 'Sin caja' }}</td>
                                    <td>
                                        {{ $session->user?->name ?? 'Sin responsable' }}
                                        <div class="table-note">Cerro: {{ $session->closedBy?->name ?? 'Sin registro' }}</div>
                                    </td>
                                    <td>
                                        <strong>Contado ${{ money($session->counted_balance) }}</strong>
                                        <div class="table-note">Diferencia ${{ money($session->difference_amount) }} | {{ $session->movements_count }} movimientos</div>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('cash-management.history.sessions.show', $session) }}" class="btn btn-sm btn-primary">
                                            Ver movimientos
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No hay cierres para los filtros seleccionados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3">
            {{ $sessions->links() }}
        </div>
    </div>
@endsection
