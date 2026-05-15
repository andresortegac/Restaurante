@extends('layouts.app')

@section('title', 'Movimiento manual - ' . config('app.name', 'Solomo & Pomo'))

@section('content')
    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">POS / Gestion de Caja</span>
                <h1>Movimiento manual en {{ $box->name }}</h1>
                <p>Registra un ingreso o egreso manual para la sesion activa de la caja. El saldo esperado se actualizara automaticamente.</p>
            </div>
            <div class="summary-group">
                <a href="{{ route('cash-management.show', $box) }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Volver a la caja
                </a>
            </div>
        </section>

        @include('products.partials.form-errors')

        <div class="card module-card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="meta-box h-100">
                            <div class="summary-kicker">Estado</div>
                            <div class="fw-bold">{{ $currentSession && $currentSession->isOpen() ? 'Sesion abierta' : 'Sesion cerrada' }}</div>
                            <div class="seat-note">{{ $currentSession?->user?->name ?? $box->user?->name ?? 'Sin responsable' }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="meta-box h-100">
                            <div class="summary-kicker">Base inicial</div>
                            <div class="fw-bold">${{ number_format((float) ($currentSession?->opening_balance ?? 0), 2) }}</div>
                            <div class="seat-note">{{ $currentSession?->opened_at ? $currentSession->opened_at->format('d/m/Y H:i') : 'Sin sesion activa' }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="meta-box h-100">
                            <div class="summary-kicker">Saldo esperado</div>
                            <div class="fw-bold">${{ number_format((float) ($currentSession?->currentBalance() ?? 0), 2) }}</div>
                            <div class="seat-note">Antes del nuevo movimiento</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($currentSession && $currentSession->isOpen())
            <div class="card module-card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-money-bill-transfer"></i> Registrar movimiento</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('cash-management.movements.store', $box) }}">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="movement_type">Tipo de movimiento</label>
                                <select class="form-select" id="movement_type" name="movement_type" required>
                                    <option value="">Selecciona una opcion</option>
                                    <option value="manual_income" @selected(old('movement_type') === 'manual_income')>Ingreso manual</option>
                                    <option value="manual_expense" @selected(old('movement_type') === 'manual_expense')>Egreso manual</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="amount">Monto</label>
                                <input type="number" step="0.01" min="0.01" class="form-control" id="amount" name="amount" value="{{ old('amount') }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="description">Motivo</label>
                                <input type="text" class="form-control" id="description" name="description" value="{{ old('description') }}" maxlength="255" required>
                            </div>
                        </div>
                        <div class="form-actions">
                            <a href="{{ route('cash-management.show', $box) }}" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Guardar movimiento</button>
                        </div>
                    </form>
                </div>
            </div>
        @else
            <div class="card module-card">
                <div class="card-body">
                    <div class="empty-state">
                        <i class="fas fa-lock"></i>
                        <h5 class="mb-2">No hay una sesion abierta</h5>
                        <p class="mb-0">Debes abrir la caja antes de registrar ingresos o egresos manuales.</p>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
