@extends('layouts.app')

@section('title', 'Cartera de ' . $customer->name . ' - RestaurantePOS')

@section('content')
    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Clientes / Cartera</span>
                <h1>{{ $customer->name }}</h1>
                <p>Administra por separado la cartera pendiente y el saldo a favor del cliente desde un solo resumen.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">${{ number_format($summary['pending'], 2) }} pendiente</span>
                <span class="summary-chip">${{ number_format($summary['available'], 2) }} saldo a favor</span>
                <span class="summary-chip">{{ $summary['pendingCount'] }} creditos pendientes</span>
                <span class="summary-chip">{{ $summary['paidCount'] }} creditos pagados</span>
            </div>
        </section>

        @include('products.partials.form-errors')

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card module-card service-card">
                    <div class="card-header d-flex justify-content-between align-items-center gap-3">
                        <div>
                            <h5 class="mb-1">Cobrar deuda del cliente</h5>
                            <p class="table-note mb-0">El pago se aplica automaticamente a los creditos pendientes mas antiguos.</p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <a href="{{ route('customers.credits.history', $customer) }}" class="btn btn-outline-primary btn-sm">Ver historial del credito</a>
                            <a href="{{ route('customers.credits.balance-history', $customer) }}" class="btn btn-outline-primary btn-sm">Ver historial del saldo a favor</a>
                            <a href="{{ route('customers.credits.index') }}" class="btn btn-outline-secondary btn-sm">Volver</a>
                        </div>
                    </div>
                    <div class="card-body">
                        @if($summary['pending'] > 0)
                            <div class="mb-4">
                                <div class="table-note text-uppercase">Deuda total</div>
                                <div class="display-6 fw-semibold mb-2">${{ number_format($summary['pending'], 2) }}</div>
                                <p class="mb-0">Puedes cobrar el valor completo o registrar un abono parcial sin perder el historial del cliente.</p>
                            </div>

                            <form method="POST" action="{{ route('customers.credits.collect', $customer) }}" data-customer-credit-collection-form data-full-amount="{{ number_format($summary['pending'], 2, '.', '') }}">
                                @csrf
                                <input type="hidden" name="payment_mode" value="{{ old('payment_mode', 'partial') }}">

                                <div class="mb-3">
                                    <label class="form-label" for="amount_received">Valor a cobrar</label>
                                    <input
                                        type="number"
                                        class="form-control"
                                        id="amount_received"
                                        name="amount_received"
                                        min="0.01"
                                        max="{{ number_format($summary['pending'], 2, '.', '') }}"
                                        step="0.01"
                                        value="{{ old('amount_received', number_format($summary['pending'], 2, '.', '')) }}"
                                        required
                                    >
                                    <div class="form-help mt-1">Si registras un abono, el sistema descuenta primero las deudas mas antiguas.</div>
                                </div>

                                <div class="d-flex flex-wrap gap-2">
                                    <button type="submit" class="btn btn-success" data-payment-mode="full">Pagar deuda completa</button>
                                    <button type="submit" class="btn btn-outline-success" data-payment-mode="partial">Registrar abono</button>
                                </div>
                            </form>
                        @else
                            <div class="empty-state py-4">
                                <i class="fas fa-check-circle"></i>
                                <h5 class="mb-2">Sin deuda pendiente</h5>
                                <p class="mb-0">Este cliente no tiene saldos por cobrar en este momento.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card module-card service-card">
                    <div class="card-header d-flex justify-content-between align-items-center gap-3">
                        <div>
                            <h5 class="mb-1">Saldo a favor</h5>
                            <p class="table-note mb-0">Este saldo se descuenta automaticamente en cobros manuales y cuentas de mesa.</p>
                        </div>
                        <span class="summary-chip">${{ number_format($summary['available'], 2) }}</span>
                    </div>
                    <div class="card-body">
                        <p class="table-note">Estos movimientos no entran ni salen de caja. Solo actualizan el saldo disponible del cliente.</p>

                        <form method="POST" action="{{ route('customers.credits.balance.store', $customer) }}">
                            @csrf

                            <div class="mb-3">
                                <label class="form-label" for="operation">Movimiento</label>
                                <select class="form-select" id="operation" name="operation">
                                    <option value="add" @selected(old('operation', 'add') === 'add')>Agregar saldo a favor</option>
                                    <option value="remove" @selected(old('operation') === 'remove')>Quitar saldo a favor</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="balance_description">Concepto</label>
                                <input type="text" class="form-control" id="balance_description" name="description" value="{{ old('description') }}" placeholder="Ej: anticipo del cliente, ajuste manual" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="balance_amount">Valor</label>
                                <input type="number" class="form-control" id="balance_amount" name="amount" min="0.01" step="0.01" value="{{ old('amount') }}" required>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Guardar movimiento de saldo a favor</button>
                        </form>
                    </div>
                </div>

                <div class="card module-card service-card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Asignar saldo pendiente</h5>
                    </div>
                    <div class="card-body">
                        <p class="table-note">Usa esta opcion para registrar una deuda manual del cliente.</p>

                        <form method="POST" action="{{ route('customers.credits.store', $customer) }}">
                            @csrf

                            <div class="mb-3">
                                <label class="form-label" for="description">Concepto</label>
                                <input type="text" class="form-control" id="description" name="description" value="{{ old('description') }}" placeholder="Ej: saldo anterior, acuerdo de pago" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="amount">Valor pendiente</label>
                                <input type="number" class="form-control" id="amount" name="amount" min="0.01" step="0.01" value="{{ old('amount') }}" required>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Guardar saldo pendiente</button>
                        </form>
                    </div>
                </div>

                <div class="card module-card service-card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Datos del cliente</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-2"><strong>Documento:</strong> {{ $customer->document_number ?: 'Sin documento' }}</div>
                        <div class="mb-2"><strong>Telefono:</strong> {{ $customer->phone ?: 'Sin telefono' }}</div>
                        <div class="mb-2"><strong>Email:</strong> {{ $customer->email ?: 'Sin email' }}</div>
                        <div><strong>Notas:</strong> {{ $customer->notes ?: 'Sin notas' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('[data-customer-credit-collection-form]');

        if (!form) {
            return;
        }

        form.addEventListener('submit', async function (event) {
            event.preventDefault();

            const submitter = event.submitter;
            const paymentModeInput = form.querySelector('input[name="payment_mode"]');
            const amountInput = form.querySelector('input[name="amount_received"]');
            const fullAmount = Number(form.dataset.fullAmount || 0);
            const paymentMode = submitter?.dataset.paymentMode || 'partial';

            if (paymentModeInput) {
                paymentModeInput.value = paymentMode;
            }

            if (paymentMode === 'full' && amountInput && fullAmount > 0) {
                amountInput.value = fullAmount.toFixed(2);
            }

            const amount = Number(amountInput?.value || 0);

            if (!amount || amount <= 0) {
                if (window.Swal) {
                    await Swal.fire({
                        icon: 'warning',
                        title: 'Falta el valor',
                        text: 'Ingresa un valor valido para registrar el cobro.',
                        confirmButtonText: 'Aceptar',
                        confirmButtonColor: '#2563eb',
                    });
                } else {
                    alert('Ingresa un valor valido para registrar el cobro.');
                }

                return;
            }

            const isFullPayment = fullAmount > 0 && Math.abs(amount - fullAmount) < 0.01;
            const confirmText = isFullPayment
                ? 'Se cobrara la deuda completa del cliente por $' + amount.toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '.'
                : 'Se registrara un abono de $' + amount.toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' para la deuda del cliente.';

            if (window.Swal) {
                const result = await Swal.fire({
                    icon: 'question',
                    title: isFullPayment ? 'Confirmar pago total' : 'Confirmar abono',
                    text: confirmText,
                    showCancelButton: true,
                    confirmButtonText: 'Registrar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#198754',
                    cancelButtonColor: '#6c757d',
                });

                if (!result.isConfirmed) {
                    return;
                }
            }

            form.submit();
        });
    });
    </script>
@endsection
