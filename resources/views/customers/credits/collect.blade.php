@extends('layouts.app')

@section('title', 'Cobrar deuda de ' . $customer->name . ' - RestaurantePOS')

@section('content')
    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Clientes / Cobrar</span>
                <h1>{{ $customer->name }}</h1>
                <p>Registra abonos o pagos completos de la deuda del cliente y genera el recibo de pago.</p>
            </div>
        </section>

        @include('products.partials.form-errors')

        <div class="row g-4">
            <div class="col-12">
                <div class="card module-card service-card">
                    <div class="card-header d-flex justify-content-between align-items-center gap-3">
                        <div>
                            <h5 class="mb-1">Cobro de deuda</h5>
                            <div class="table-note">El ingreso se registra en la caja abierta.</div>
                        </div>
                        <a href="{{ route('customers.credits.payments.history', $customer) }}" class="btn btn-outline-primary btn-sm">Historial de pago</a>
                    </div>
                    <div class="card-body">
                        @if($summary['pending'] <= 0)
                            <div class="empty-state py-4">
                                <i class="fas fa-check-circle"></i>
                                <h5 class="mb-2">Sin deuda pendiente</h5>
                                <p class="mb-0">Este cliente no tiene saldos por cobrar en este momento.</p>
                            </div>
                        @else
                            <form method="POST" action="{{ route('customers.credits.collect.store', $customer) }}">
                                @csrf

                                <div class="mb-3">
                                    <label class="form-label" for="payment_mode">Tipo de cobro</label>
                                    <select class="form-select" id="payment_mode" name="payment_mode">
                                        <option value="full" @selected(old('payment_mode', 'full') === 'full')>Pago completo</option>
                                        <option value="partial" @selected(old('payment_mode') === 'partial')>Abono parcial</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="amount_received">Valor recibido</label>
                                    <input type="number" class="form-control" id="amount_received" name="amount_received" min="1" max="{{ money_input($summary['pending']) }}" step="1" value="{{ money_input(old('amount_received', $summary['pending'])) }}" data-total="{{ money_input($summary['pending']) }}" required>
                                    <div class="form-text">Maximo: ${{ money($summary['pending']) }}</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="payment_method_id">Metodo de pago</label>
                                    <select class="form-select" id="payment_method_id" name="payment_method_id">
                                        <option value="">Efectivo</option>
                                        @foreach($paymentMethods as $paymentMethod)
                                            @if(strtoupper((string) $paymentMethod->code) !== 'CASH')
                                                <option value="{{ $paymentMethod->id }}" @selected((string) old('payment_method_id') === (string) $paymentMethod->id)>
                                                    {{ $paymentMethod->name }}
                                                </option>
                                            @endif
                                        @endforeach
                                    </select>
                                    <div class="form-text">Solo efectivo aumenta el saldo fisico de caja.</div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label" for="reference">Nota</label>
                                    <input type="text" class="form-control" id="reference" name="reference" value="{{ old('reference') }}" maxlength="255" placeholder="Nota del pago">
                                </div>

                                <button type="submit" class="btn btn-success w-100">
                                    Cobrar y generar recibo
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const paymentMode = document.getElementById('payment_mode');
            const amountReceived = document.getElementById('amount_received');

            if (! paymentMode || ! amountReceived) {
                return;
            }

            const syncAmountState = function () {
                const isFullPayment = paymentMode.value === 'full';

                if (isFullPayment) {
                    amountReceived.value = amountReceived.dataset.total || amountReceived.max;
                }

                amountReceived.readOnly = isFullPayment;
            };

            paymentMode.addEventListener('change', syncAmountState);
            syncAmountState();
        });
    </script>
@endpush
