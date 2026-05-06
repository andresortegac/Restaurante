@extends('layouts.app')

@section('title', $box->name . ' - Gestion de Caja')

@section('content')
    @php
        $canManageBoxCatalog = Auth::user()->hasRole('Admin');
        $expectedClosingBalance = (float) $currentBalance;
        $closingDifference = $currentSession && ! $currentSession->isOpen()
            ? (float) $currentSession->difference_amount
            : 0;
    @endphp

    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">POS / Gestion de Caja</span>
                <h1>{{ $box->name }}</h1>
                <p>{{ $box->code }}. Configura la caja una sola vez y luego opera el turno diario con apertura, movimientos y cierre conciliado.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">{{ $currentSession?->isOpen() ? 'Sesion abierta' : 'Sesion cerrada' }}</span>
                <span class="summary-chip">${{ number_format($incomeTotal, 2) }} ingresos</span>
                <span class="summary-chip">${{ number_format($expenseTotal, 2) }} egresos</span>
                <span class="summary-chip">${{ number_format($currentBalance, 2) }} saldo actual</span>
                @if($canManageBoxCatalog)
                    <a href="{{ route('cash-management.edit', $box) }}" class="btn btn-outline-secondary">
                        <i class="fas fa-pen"></i> Editar caja
                    </a>
                @endif
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
                        <div class="meta-box mb-3">
                            <div class="summary-kicker">Diferencia clave</div>
                            <div class="seat-note">La caja es el punto fisico. La sesion es el turno diario que abres y cierras.</div>
                        </div>
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
                            <div class="module-list-item">
                                <div>
                                    <strong>Saldo esperado</strong>
                                    <div class="table-note">${{ number_format((float) $currentBalance, 2) }}</div>
                                </div>
                            </div>
                            @if($currentSession && ! $currentSession->isOpen())
                                <div class="module-list-item">
                                    <div>
                                        <strong>Diferencia del ultimo cierre</strong>
                                        <div class="table-note">${{ number_format((float) $currentSession->difference_amount, 2) }}</div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="card module-card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-list-check"></i> Flujo diario</h5>
                    </div>
                    <div class="card-body">
                        <div class="module-list">
                            <div class="module-list-item">
                                <div>
                                    <strong>1. Abrir sesion</strong>
                                    <div class="table-note">Ingresa la base con la que arranca el turno.</div>
                                </div>
                                <span class="badge rounded-pill {{ $currentSession && $currentSession->isOpen() ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $currentSession && $currentSession->isOpen() ? 'Hecho' : 'Pendiente' }}
                                </span>
                            </div>
                            <div class="module-list-item">
                                <div>
                                    <strong>2. Operar la caja</strong>
                                    <div class="table-note">Las ventas y movimientos manuales alimentan el saldo esperado.</div>
                                </div>
                                <span class="badge rounded-pill {{ $currentSession && $currentSession->isOpen() ? 'bg-primary' : 'bg-secondary' }}">
                                    {{ $currentSession && $currentSession->isOpen() ? 'En curso' : 'Bloqueado' }}
                                </span>
                            </div>
                            <div class="module-list-item">
                                <div>
                                    <strong>3. Cerrar turno</strong>
                                    <div class="table-note">Cuenta el efectivo, compara contra el sistema y registra la diferencia.</div>
                                </div>
                                <span class="badge rounded-pill {{ $currentSession && ! $currentSession->isOpen() ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $currentSession && ! $currentSession->isOpen() ? 'Ultimo cierre listo' : 'Pendiente' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card module-card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-clock-rotate-left"></i> Ultimas sesiones</h5>
                    </div>
                    <div class="card-body">
                        <div class="module-list">
                            @forelse($recentSessions as $session)
                                <div class="module-list-item">
                                    <div>
                                        <strong>{{ $session->opened_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}</strong>
                                        <div class="table-note">Abierta por {{ $session->user?->name ?? 'Sin responsable' }}</div>
                                        <div class="table-note">Base ${{ number_format((float) $session->opening_balance, 2) }}</div>
                                    </div>
                                    <div class="text-end">
                                        <div class="table-note">{{ $session->closed_at?->format('d/m/Y H:i') ?? 'Sesion abierta' }}</div>
                                        <span class="summary-chip">${{ number_format((float) ($session->counted_balance ?? $session->currentBalance()), 2) }}</span>
                                    </div>
                                </div>
                            @empty
                                <p class="text-muted mb-0">Aun no hay sesiones registradas para esta caja.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                @if(!$currentSession || ! $currentSession->isOpen())
                    <div class="card module-card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0"><i class="fas fa-lock-open"></i> 1. Abrir sesion del dia</h5>
                        </div>
                        <div class="card-body">
                            <p class="table-note">Usa esta accion al iniciar turno. La base es el efectivo con el que arranca la caja ese dia.</p>
                            <form method="POST" action="{{ route('cash-management.open', $box) }}" data-open-session-form>
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label" for="opening_balance">Base de caja</label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="opening_balance" name="opening_balance" value="{{ old('opening_balance', 0) }}" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="opening_notes">Observaciones</label>
                                    <textarea class="form-control" id="opening_notes" name="opening_notes" rows="3">{{ old('opening_notes') }}</textarea>
                                </div>
                                <div class="table-note mb-3">Solo puedes tener una sesion abierta por usuario. Si ya abriste otra caja con tu cuenta, primero debes cerrarla.</div>
                                <button type="submit" class="btn btn-primary w-100" data-open-session-submit>Abrir caja</button>
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
                                    <h5 class="card-title mb-0"><i class="fas fa-plus-circle"></i> 2. Ingreso manual</h5>
                                </div>
                                <div class="card-body">
                                    <p class="table-note">Registra entradas que no nacen de una venta, por ejemplo fondo adicional o ajuste controlado.</p>
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
                                    <h5 class="card-title mb-0"><i class="fas fa-minus-circle"></i> 2. Egreso manual</h5>
                                </div>
                                <div class="card-body">
                                    <p class="table-note">Registra salidas controladas como compra de cambio, domicilios pagados en efectivo o gastos menores.</p>
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
                                    <h5 class="card-title mb-0"><i class="fas fa-right-from-bracket"></i> 3. Cierre diario</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-3">
                                            <div class="meta-box h-100">
                                                <div class="summary-kicker">Base inicial</div>
                                                <div class="fw-bold">${{ number_format((float) $currentSession->opening_balance, 2) }}</div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="meta-box h-100">
                                                <div class="summary-kicker">Ventas del turno</div>
                                                <div class="fw-bold">${{ number_format((float) $automaticIncome, 2) }}</div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="meta-box h-100">
                                                <div class="summary-kicker">Ingresos manuales</div>
                                                <div class="fw-bold">${{ number_format((float) $manualIncome, 2) }}</div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="meta-box h-100">
                                                <div class="summary-kicker">Egresos manuales</div>
                                                <div class="fw-bold">${{ number_format((float) $manualExpense, 2) }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="meta-box mb-3">
                                        <div class="d-flex flex-wrap justify-content-between gap-3">
                                            <div>
                                                <div class="summary-kicker">Saldo esperado segun sistema</div>
                                                <div class="h5 mb-0">${{ number_format($expectedClosingBalance, 2) }}</div>
                                            </div>
                                            <div>
                                                <div class="summary-kicker">Diferencia estimada</div>
                                                <div class="h5 mb-0" id="closingDifferencePreview">${{ number_format($closingDifference, 2) }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    <p class="table-note">Cuenta el efectivo real al final del turno, escribelo aqui y el sistema conciliara automaticamente contra el saldo esperado.</p>
                                    <form method="POST" action="{{ route('cash-management.close', $box) }}" class="row g-3">
                                        @csrf
                                        <div class="col-md-4">
                                            <label class="form-label" for="counted_balance">Valor contado fisicamente</label>
                                            <input type="number" step="0.01" min="0" class="form-control" id="counted_balance" name="counted_balance" value="{{ old('counted_balance', number_format($expectedClosingBalance, 2, '.', '')) }}" required>
                                        </div>
                                        <div class="col-md-8">
                                            <label class="form-label" for="closing_notes">Observaciones</label>
                                            <input type="text" class="form-control" id="closing_notes" name="closing_notes">
                                        </div>
                                        <div class="col-12">
                                            <div class="table-note">Si el valor contado coincide con el saldo esperado, la diferencia quedara en $0.00.</div>
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
                        <h5 class="card-title mb-0"><i class="fas fa-list"></i> Movimientos de la sesion</h5>
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

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const openSessionForm = document.querySelector('[data-open-session-form]');

            if (openSessionForm) {
                openSessionForm.addEventListener('submit', function () {
                    const submitButton = openSessionForm.querySelector('[data-open-session-submit]');

                    if (! submitButton || submitButton.disabled) {
                        return;
                    }

                    submitButton.disabled = true;
                    submitButton.textContent = 'Abriendo caja...';
                });
            }

            const countedBalanceInput = document.getElementById('counted_balance');
            const differencePreview = document.getElementById('closingDifferencePreview');
            const expectedBalance = {{ json_encode($expectedClosingBalance) }};

            if (! countedBalanceInput || ! differencePreview) {
                return;
            }

            function syncDifferencePreview() {
                const countedBalance = Number(countedBalanceInput.value || 0);
                const difference = countedBalance - Number(expectedBalance || 0);
                differencePreview.textContent = new Intl.NumberFormat('es-CO', {
                    style: 'currency',
                    currency: 'COP',
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                }).format(difference);

                differencePreview.classList.remove('text-success', 'text-danger', 'text-muted', 'text-primary');

                if (Math.abs(difference) < 0.009) {
                    differencePreview.classList.add('text-success');
                } else if (difference > 0) {
                    differencePreview.classList.add('text-primary');
                } else {
                    differencePreview.classList.add('text-danger');
                }
            }

            countedBalanceInput.addEventListener('input', syncDifferencePreview);
            syncDifferencePreview();
        });
    </script>
@endsection
