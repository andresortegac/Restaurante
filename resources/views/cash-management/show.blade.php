@extends('layouts.app')

@section('title', $box->name . ' - ' . config('app.name', 'Solomo & Pomo'))

@section('content')
    @php
        $canManageBoxCatalog = Auth::user()->hasRole('Admin');
        $expectedClosingBalance = (float) $currentBalance;
        $closingDifference = $currentSession && ! $currentSession->isOpen()
            ? (float) $currentSession->difference_amount
            : 0;
        $requestedPanel = request('panel');
        $showManualMovement = $currentSession && $currentSession->isOpen() && $canRegisterMovements;
        $showClosing = $currentSession && $currentSession->isOpen();
        $bankTransferTotal = $paymentBreakdown
            ->where('code', 'TRANSFER')
            ->sum('total');
        $defaultCashMethodId = $paymentMethods
            ->firstWhere('code', 'CASH')
            ?->id;
        $selectedManualPaymentMethodId = old('payment_method_id', $defaultCashMethodId);
    @endphp

    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">POS / Gestion de Caja</span>
                <h1>{{ $box->name }}</h1>
                @if($box->description)
                    <p>{{ $box->description }}</p>
                @endif
            </div>
            <div class="summary-group">
                <a href="{{ route('cash-management.index') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>
        </section>

        @include('products.partials.form-errors')

        <div class="d-flex flex-column gap-4">
            @if(!$currentSession || ! $currentSession->isOpen())
                <div class="card module-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-lock-open"></i> 1. Abrir sesion del dia</h5>
                    </div>
                    <div class="card-body">
                        <p class="table-note">Usa esta accion al iniciar turno. La base es el efectivo con el que arranca la caja ese dia.</p>
                        <form method="POST" action="{{ route('cash-management.open', $box) }}" data-open-session-form>
                            @csrf
                            <div class="mb-3">
                                <label class="form-label" for="opening_balance">Base de caja</label>
                                <input type="number" step="1" min="0" class="form-control" id="opening_balance" name="opening_balance" value="{{ money_input(old('opening_balance', 200000)) }}" required>
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

            @if($currentSession)
                <div class="card module-card">
                    <div class="card-body py-3">
                        <div class="d-flex flex-wrap align-items-center gap-3">
                            <strong><i class="fas fa-circle-info"></i> Estado</strong>
                            <span class="table-note">Responsable: {{ $currentSession?->user?->name ?? $box->user?->name ?? 'Sin responsable' }}</span>
                            <span class="table-note">Apertura: {{ $currentSession->opened_at ? $currentSession->opened_at->format('d/m/Y H:i') : 'Sin sesion activa' }}</span>
                            <span class="table-note">Base: ${{ money($currentSession->opening_balance) }}</span>
                            <span class="table-note">Saldo: ${{ money($currentBalance) }}</span>
                            @if(! $currentSession->isOpen())
                                <span class="table-note">Diferencia: ${{ money($currentSession->difference_amount) }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            @if($currentSession && $currentSession->isOpen())
                @if($showManualMovement)
                    <div class="card module-card" id="manual-movement">
                        <div class="card-header">
                            <h5 class="card-title mb-0"><i class="fas fa-money-bill-transfer"></i> Movimiento manual</h5>
                        </div>
                        <div class="card-body">
                            <p class="table-note">Registra ingresos o egresos del turno sin salir del detalle de la caja. El saldo esperado se actualizara automaticamente.</p>

                            <div class="table-note mb-3">Saldo esperado actual: ${{ money($currentBalance) }}</div>

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
                                        <input type="number" step="1" min="1" class="form-control" id="amount" name="amount" value="{{ money_input(old('amount', 0)) }}" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="payment_method_id">Metodo de pago</label>
                                        <select class="form-select" id="payment_method_id" name="payment_method_id">
                                            @foreach($paymentMethods as $paymentMethod)
                                                <option value="{{ $paymentMethod->id }}" @selected((string) $selectedManualPaymentMethodId === (string) $paymentMethod->id)>
                                                    {{ $paymentMethod->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label" for="description">Motivo</label>
                                        <textarea class="form-control" id="description" name="description" rows="3" maxlength="255" required>{{ old('description') }}</textarea>
                                    </div>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">Guardar movimiento</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif

                @if($showClosing)
                <div class="card module-card" id="closing-session">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-right-from-bracket"></i> Cierre diario</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <div class="meta-box h-100">
                                    <div class="summary-kicker">Base inicial</div>
                                    <div class="fw-bold">${{ money($currentSession->opening_balance) }}</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="meta-box h-100">
                                    <div class="summary-kicker">Transferencias bancarias</div>
                                    <div class="fw-bold">${{ money($bankTransferTotal) }}</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="meta-box h-100">
                                    <div class="summary-kicker">Ingresos manuales</div>
                                    <div class="fw-bold">${{ money($manualIncome) }}</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="meta-box h-100">
                                    <div class="summary-kicker">Egresos manuales</div>
                                    <div class="fw-bold">${{ money($manualExpense) }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="meta-box mb-3">
                            <div class="d-flex flex-wrap justify-content-between gap-3">
                                <div>
                                    <div class="summary-kicker">Saldo esperado ventas en efectivo</div>
                                    <div class="h5 mb-0">${{ money($expectedClosingBalance) }}</div>
                                </div>
                            </div>
                        </div>

                        <p class="table-note">Cuenta el efectivo real al final del turno, escribelo aqui y el sistema conciliara automaticamente contra el saldo esperado.</p>
                        <form method="POST" action="{{ route('cash-management.close', $box) }}" class="row g-3">
                            @csrf
                            <div class="col-md-4">
                                <label class="form-label" for="counted_balance">Valor contado fisicamente</label>
                                <input type="number" step="1" min="0" class="form-control" id="counted_balance" name="counted_balance" value="{{ money_input(old('counted_balance', $expectedClosingBalance)) }}" required>
                                <div class="form-text" id="closingDifferencePreview"></div>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label" for="closing_notes">Observaciones</label>
                                <input type="text" class="form-control" id="closing_notes" name="closing_notes">
                            </div>
                            <div class="col-12">
                                <div class="d-flex flex-wrap gap-2">
                                    <button type="submit" class="btn btn-primary">Cerrar caja</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                @endif
            @endif
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

            countedBalanceInput.value = countedBalanceInput.value || expectedBalance;

            function syncDifferencePreview() {
                const countedBalance = Number(countedBalanceInput.value || 0);
                const difference = Math.round(countedBalance - Number(expectedBalance || 0));
                const absoluteDifference = Math.abs(difference).toLocaleString('es-CO');

                differencePreview.classList.remove('text-success', 'text-danger', 'text-primary');

                if (difference === 0) {
                    differencePreview.textContent = 'Sin diferencia.';
                    differencePreview.classList.add('text-success');
                    return;
                }

                if (difference > 0) {
                    differencePreview.textContent = 'Sobra $' + absoluteDifference + ' frente al saldo esperado.';
                    differencePreview.classList.add('text-primary');
                    return;
                }

                differencePreview.textContent = 'Falta $' + absoluteDifference + ' frente al saldo esperado.';
                differencePreview.classList.add('text-danger');
            }

            countedBalanceInput.addEventListener('input', syncDifferencePreview);
            syncDifferencePreview();
        });
    </script>
@endsection
