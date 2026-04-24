@extends('layouts.app')

@section('title', $box->name . ' - Gestion de Caja')

@section('content')
    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">POS / Gestion de Caja</span>
                <h1>{{ $box->name }}</h1>
                <p>{{ $box->code }}. Administra la sesion actual, registra movimientos manuales y realiza el cierre con conciliacion.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">{{ $currentSession?->isOpen() ? 'Sesion abierta' : 'Sesion cerrada' }}</span>
                <span class="summary-chip">${{ number_format($incomeTotal, 2) }} ingresos</span>
                <span class="summary-chip">${{ number_format($expenseTotal, 2) }} egresos</span>
                <span class="summary-chip">${{ number_format($currentBalance, 2) }} saldo actual</span>
            </div>
        </section>

        @include('products.partials.form-errors')

        <div class="row g-4">
            <div class="col-xl-4">
                <div class="card module-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-circle-info"></i> Estado</h5>
                    </div>
                    <div class="card-body">
                        <div class="module-list">
                            <div class="module-list-item">
                                <div>
                                    <strong>Responsable</strong>
                                    <div class="table-note">{{ $currentSession?->user?->name ?? $box->user?->name ?? 'Sin responsable' }}</div>
                                </div>
                            </div>
                            <div class="module-list-item">
                                <div>
                                    <strong>Apertura</strong>
                                    <div class="table-note">{{ $currentSession?->opened_at ? $currentSession->opened_at->format('d/m/Y H:i') : 'Sin sesion activa' }}</div>
                                </div>
                            </div>
                            <div class="module-list-item">
                                <div>
                                    <strong>Base inicial</strong>
                                    <div class="table-note">${{ number_format((float) ($currentSession?->opening_balance ?? 0), 2) }}</div>
                                </div>
                            </div>
                            @if($currentSession && ! $currentSession->isOpen())
                                <div class="module-list-item">
                                    <div>
                                        <strong>Diferencia</strong>
                                        <div class="table-note">${{ number_format((float) $currentSession->difference_amount, 2) }}</div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                @if(!$currentSession || ! $currentSession->isOpen())
                    <div class="card module-card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0"><i class="fas fa-lock-open"></i> Abrir caja</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('cash-management.open', $box) }}">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label" for="opening_balance">Base de caja</label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="opening_balance" name="opening_balance" value="{{ old('opening_balance', 0) }}" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="opening_notes">Observaciones</label>
                                    <textarea class="form-control" id="opening_notes" name="opening_notes" rows="3">{{ old('opening_notes') }}</textarea>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Abrir caja</button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-xl-8">
                @if($currentSession && $currentSession->isOpen())
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="card module-card h-100">
                                <div class="card-header">
                                    <h5 class="card-title mb-0"><i class="fas fa-plus-circle"></i> Ingreso manual</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="{{ route('cash-management.movements.store', $box) }}">
                                        @csrf
                                        <input type="hidden" name="movement_type" value="manual_income">
                                        <div class="mb-3">
                                            <label class="form-label" for="income_amount">Monto</label>
                                            <input type="number" step="0.01" min="0.01" class="form-control" id="income_amount" name="amount" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label" for="income_description">Motivo</label>
                                            <input type="text" class="form-control" id="income_description" name="description" required>
                                        </div>
                                        <button type="submit" class="btn btn-outline-success w-100">Registrar ingreso</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card module-card h-100">
                                <div class="card-header">
                                    <h5 class="card-title mb-0"><i class="fas fa-minus-circle"></i> Egreso manual</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="{{ route('cash-management.movements.store', $box) }}">
                                        @csrf
                                        <input type="hidden" name="movement_type" value="manual_expense">
                                        <div class="mb-3">
                                            <label class="form-label" for="expense_amount">Monto</label>
                                            <input type="number" step="0.01" min="0.01" class="form-control" id="expense_amount" name="amount" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label" for="expense_description">Motivo</label>
                                            <input type="text" class="form-control" id="expense_description" name="description" required>
                                        </div>
                                        <button type="submit" class="btn btn-outline-danger w-100">Registrar egreso</button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="card module-card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0"><i class="fas fa-right-from-bracket"></i> Cierre diario</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="{{ route('cash-management.close', $box) }}" class="row g-3">
                                        @csrf
                                        <div class="col-md-4">
                                            <label class="form-label" for="counted_balance">Valor contado fisicamente</label>
                                            <input type="number" step="0.01" min="0" class="form-control" id="counted_balance" name="counted_balance" required>
                                        </div>
                                        <div class="col-md-8">
                                            <label class="form-label" for="closing_notes">Observaciones</label>
                                            <input type="text" class="form-control" id="closing_notes" name="closing_notes">
                                        </div>
                                        <div class="col-12">
                                            <div class="table-note">Saldo esperado segun sistema: ${{ number_format($currentBalance, 2) }}</div>
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-primary">Cerrar caja</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="card module-card mt-4 mt-xl-0">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-list"></i> Movimientos</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Tipo</th>
                                        <th>Detalle</th>
                                        <th>Monto</th>
                                        <th>Saldo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($movements as $movement)
                                        <tr>
                                            <td>{{ $movement->occurred_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                            <td>{{ str_replace('_', ' ', $movement->movement_type) }}</td>
                                            <td>
                                                <strong>{{ $movement->description ?: 'Sin detalle' }}</strong>
                                                <div class="table-note">{{ $movement->user?->name ?? 'Sistema' }}</div>
                                            </td>
                                            <td class="{{ $movement->amount >= 0 ? 'text-success' : 'text-danger' }}">
                                                ${{ number_format(abs((float) $movement->amount), 2) }}
                                            </td>
                                            <td>${{ number_format((float) $movement->balance_after, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">Todavia no hay movimientos para esta sesion.</td>
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
        </div>
    </div>
@endsection
