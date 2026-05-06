@extends('layouts.app')

@section('title', 'Reservas - RestaurantePOS')

@section('content')
    @php
        $user = Auth::user();
        $canCreateReservation = $user->hasRole('Admin') || $user->hasPermission('reservations.create');
        $canEditReservation = $user->hasRole('Admin') || $user->hasPermission('reservations.edit');
        $canDeleteReservation = $user->hasRole('Admin') || $user->hasPermission('reservations.delete');
        $statusClasses = [
            'pending' => 'bg-warning text-dark',
            'confirmed' => 'bg-primary',
            'seated' => 'bg-info text-dark',
            'completed' => 'bg-success',
            'cancelled' => 'bg-secondary',
            'no_show' => 'bg-dark',
        ];
    @endphp

    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Reservas / Agenda del salon</span>
                <h1>Gestion de reservas</h1>
                <p>Programa apartados de mesas, registra datos de contacto y organiza la llegada de los clientes por fecha y hora.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">{{ $summary['total'] }} registradas</span>
                <span class="summary-chip">{{ $summary['today'] }} para hoy</span>
                <span class="summary-chip">{{ $summary['upcoming'] }} proximas</span>
            </div>
        </section>

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="order-summary-card h-100">
                    <div class="summary-kicker">Total</div>
                    <div class="summary-value">{{ $summary['total'] }}</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="order-summary-card h-100">
                    <div class="summary-kicker">Hoy</div>
                    <div class="summary-value">{{ $summary['today'] }}</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="order-summary-card h-100">
                    <div class="summary-kicker">Proximas</div>
                    <div class="summary-value">{{ $summary['upcoming'] }}</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="order-summary-card h-100">
                    <div class="summary-kicker">Confirmadas</div>
                    <div class="summary-value">{{ $summary['confirmed'] }}</div>
                </div>
            </div>
        </div>

        <div class="module-toolbar">
            <form method="GET" action="{{ route('reservations.index') }}" class="row g-2 align-items-end flex-grow-1">
                <div class="col-md-5">
                    <label class="form-label" for="search">Buscar</label>
                    <input type="text" class="form-control" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Cliente, telefono, email u observaciones">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="status">Estado</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos</option>
                        @foreach($statusLabels as $status => $label)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="date">Fecha</label>
                    <input type="date" class="form-control" id="date" name="date" value="{{ $filters['date'] ?? '' }}">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary w-100">Filtrar</button>
                    <a href="{{ route('reservations.index') }}" class="btn btn-outline-secondary w-100">Limpiar</a>
                </div>
            </form>

            @if($canCreateReservation)
                <a href="{{ route('reservations.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nueva reserva
                </a>
            @endif
        </div>

        <div class="card module-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Reserva</th>
                                <th>Mesa</th>
                                <th>Observaciones</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reservations as $reservation)
                                <tr>
                                    <td>
                                        <strong>{{ $reservation->customer_name }}</strong>
                                        <div class="table-note">{{ $reservation->customer_phone ?: 'Sin telefono' }}</div>
                                        <div class="table-note">{{ $reservation->customer_email ?: 'Sin email' }}</div>
                                    </td>
                                    <td>
                                        <div>{{ optional($reservation->reservation_at)->format('d/m/Y h:i A') }}</div>
                                        <div class="table-note">{{ $reservation->party_size }} personas</div>
                                        <div class="table-note">Abono ${{ number_format((float) ($reservation->deposit_amount ?? 0), 2) }}</div>
                                    </td>
                                    <td>
                                        @if($reservation->table)
                                            <div>{{ $reservation->table->name }}</div>
                                            <div class="table-note">{{ $reservation->table->area ?: 'Salon principal' }} · Cap. {{ $reservation->table->capacity }}</div>
                                        @else
                                            <span class="text-muted">Sin asignar</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div>{{ $reservation->notes ?: 'Sin observaciones' }}</div>
                                        <div class="table-note">Registrada por {{ $reservation->reservedBy?->name ?: 'Sin usuario' }}</div>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill {{ $statusClasses[$reservation->status] ?? 'bg-secondary' }}">
                                            {{ $statusLabels[$reservation->status] ?? ucfirst($reservation->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="table-actions justify-content-end">
                                            @if($canEditReservation)
                                                <a href="{{ route('reservations.edit', $reservation) }}" class="btn btn-outline-primary btn-sm">Editar</a>
                                            @endif
                                            @if($canDeleteReservation)
                                                <form method="POST" action="{{ route('reservations.destroy', $reservation) }}" onsubmit="return confirm('Deseas eliminar esta reserva?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-outline-danger btn-sm">Eliminar</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">Todavia no hay reservas registradas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3">
            {{ $reservations->links() }}
        </div>
    </div>
@endsection
