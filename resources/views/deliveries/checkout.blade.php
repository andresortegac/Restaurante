@extends('layouts.app')

@section('title', 'Cobro ' . $delivery->delivery_number . ' - Domicilios')

@section('content')
@php
    $baseTotal = (float) $delivery->total_charge;
    $initialReceived = (float) old('amount_received', $delivery->customer_payment_amount > 0 ? $delivery->customer_payment_amount : $baseTotal);
    $initialChange = max(0, $initialReceived - $baseTotal);
    $paymentMethodOptions = $paymentMethods->map(fn ($method) => [
        'id' => (int) $method->id,
        'name' => $method->name,
        'code' => strtoupper((string) $method->code),
    ])->values();
@endphp

<div class="module-page">
    <section class="module-hero">
        <div>
            <span class="module-kicker">Caja / Domicilios</span>
            <h1>Cobro de {{ $delivery->delivery_number }}</h1>
            <p>Registra el pago del domicilio, calcula el cambio en tiempo real y deja el ticket listo para imprimir.</p>
        </div>
        <div class="summary-group">
            <span class="summary-chip">{{ $delivery->deliveryDriver?->name ?? 'Sin domiciliario' }}</span>
            <span class="summary-chip">{{ $delivery->customer_name }}</span>
            <span class="summary-chip">Total ${{ number_format($baseTotal, 2) }}</span>
        </div>
    </section>

    @include('products.partials.form-errors')

    <div class="table-detail-layout">
        <div>
            <div class="card module-card service-card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h5 class="mb-1">Resumen del domicilio</h5>
                        <p class="table-note mb-0">
                            {{ $delivery->delivery_address }}
                            @if($delivery->reference)
                                | Referencia: {{ $delivery->reference }}
                            @endif
                        </p>
                    </div>
                    <a href="{{ route('deliveries.index') }}" class="btn btn-outline-secondary btn-sm">Volver a domicilios</a>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="order-summary-card h-100">
                                <div class="summary-kicker">Pedido</div>
                                <div class="h3 mb-0">${{ number_format((float) $delivery->order_total, 2) }}</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="order-summary-card h-100">
                                <div class="summary-kicker">Domicilio</div>
                                <div class="h3 mb-0">${{ number_format((float) $delivery->delivery_fee, 2) }}</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="order-summary-card h-100">
                                <div class="summary-kicker">Total a cobrar</div>
                                <div class="h3 mb-0">${{ number_format($baseTotal, 2) }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive mt-4">
                        <table class="table table-hover order-items-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Cantidad</th>
                                    <th>Precio</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($delivery->items as $item)
                                    <tr>
                                        <td><strong>{{ $item->product_name }}</strong></td>
                                        <td>{{ $item->quantity }}</td>
                                        <td>${{ number_format((float) $item->unit_price, 2) }}</td>
                                        <td class="text-end">${{ number_format((float) $item->subtotal, 2) }}</td>
                                    </tr>
                                @endforeach
                                @if((float) $delivery->delivery_fee > 0)
                                    <tr>
                                        <td><strong>Costo domicilio</strong></td>
                                        <td>1</td>
                                        <td>${{ number_format((float) $delivery->delivery_fee, 2) }}</td>
                                        <td class="text-end">${{ number_format((float) $delivery->delivery_fee, 2) }}</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <aside>
            <div class="card module-card service-card">
                <div class="card-header">
                    <h5 class="mb-0">Registrar cobro</h5>
                </div>
                <div class="card-body">
                    @if(!$activeBox)
                        <div class="empty-state py-4">
                            <i class="fas fa-cash-register"></i>
                            <h5 class="mb-2">No hay caja abierta</h5>
                            <p class="mb-0">Abre una caja antes de cobrar este domicilio.</p>
                        </div>
                    @else
                        <div class="meta-box mb-3">
                            <div class="summary-kicker">Caja activa</div>
                            <div class="fw-bold">{{ $activeBox->name }}</div>
                            <div class="seat-note">{{ $activeBox->description ?: 'Sin descripcion operativa registrada.' }}</div>
                            <div class="seat-note">Saldo estimado: ${{ number_format($activeBox->currentBalance(), 2) }}</div>
                        </div>

                        <form method="POST" action="{{ route('deliveries.checkout.store', $delivery) }}" id="deliveryCheckoutForm">
                            @csrf

                            <div class="mb-3">
                                <label class="form-label" for="payment_method_id">Metodo de pago</label>
                                <select class="form-select" id="payment_method_id" name="payment_method_id" required>
                                    <option value="">Selecciona un metodo</option>
                                    @foreach($paymentMethods as $paymentMethod)
                                        <option value="{{ $paymentMethod->id }}" data-payment-code="{{ strtoupper((string) $paymentMethod->code) }}" @selected((int) old('payment_method_id') === (int) $paymentMethod->id)>
                                            {{ $paymentMethod->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="amount_received">Monto recibido</label>
                                <input type="number" class="form-control" id="amount_received" name="amount_received" min="0" step="0.01" value="{{ number_format($initialReceived, 2, '.', '') }}" required>
                                <div class="form-help mt-1" id="amountReceivedHelp">Ingresa el valor recibido y veras de inmediato el cambio a devolver.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="reference">Referencia</label>
                                <input type="text" class="form-control" id="reference" name="reference" value="{{ old('reference') }}" placeholder="Ultimos digitos, comprobante o nota del pago">
                            </div>

                            <div class="meta-box mb-3">
                                <div class="d-flex justify-content-between gap-3">
                                    <span class="summary-kicker">Total domicilio</span>
                                    <strong id="checkoutAmountDue">${{ number_format($baseTotal, 2) }}</strong>
                                </div>
                                <div class="d-flex justify-content-between gap-3 mt-2">
                                    <span class="summary-kicker">Cambio a devolver</span>
                                    <strong id="checkoutChange">${{ number_format($initialChange, 2) }}</strong>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-success w-100">Registrar cobro y generar ticket</button>
                        </form>
                    @endif
                </div>
            </div>
        </aside>
    </div>
</div>

@if($activeBox)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const paymentMethods = @json($paymentMethodOptions);
    const baseTotal = Number(@json($baseTotal));
    const methodSelect = document.getElementById('payment_method_id');
    const amountReceivedInput = document.getElementById('amount_received');
    const amountDueLabel = document.getElementById('checkoutAmountDue');
    const changeLabel = document.getElementById('checkoutChange');
    const amountReceivedHelp = document.getElementById('amountReceivedHelp');

    if (!methodSelect || !amountReceivedInput || !amountDueLabel || !changeLabel) {
        return;
    }

    const money = value => '$' + Number(value || 0).toLocaleString('es-CO', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    const selectedMethod = () => paymentMethods.find(method => String(method.id) === String(methodSelect.value)) || null;
    const isCashPayment = () => {
        const method = selectedMethod();
        return !method || method.code === 'CASH';
    };

    const syncAmounts = () => {
        const method = selectedMethod();
        const cashPayment = isCashPayment();
        const requiresExactAmount = Boolean(method) && !cashPayment;

        if (requiresExactAmount) {
            amountReceivedInput.value = baseTotal.toFixed(2);
            amountReceivedInput.readOnly = true;
            amountReceivedInput.classList.add('bg-light');
            amountReceivedHelp.textContent = 'Para pagos distintos a efectivo, el monto recibido debe coincidir con el total a cobrar.';
        } else {
            const currentValue = Math.max(0, Number(amountReceivedInput.value || 0));

            if (currentValue < baseTotal || amountReceivedInput.dataset.userEdited !== 'true') {
                amountReceivedInput.value = baseTotal.toFixed(2);
            }

            amountReceivedInput.readOnly = false;
            amountReceivedInput.classList.remove('bg-light');
            amountReceivedHelp.textContent = 'Ingresa el valor recibido y veras de inmediato el cambio a devolver.';
        }

        const receivedAmount = Math.max(0, Number(amountReceivedInput.value || 0));
        const changeAmount = cashPayment ? Math.max(0, receivedAmount - baseTotal) : 0;

        amountDueLabel.textContent = money(baseTotal);
        changeLabel.textContent = money(changeAmount);
    };

    amountReceivedInput.addEventListener('input', function () {
        amountReceivedInput.dataset.userEdited = 'true';
        syncAmounts();
    });

    methodSelect.addEventListener('change', function () {
        if (!isCashPayment()) {
            amountReceivedInput.dataset.userEdited = 'false';
        }

        syncAmounts();
    });

    syncAmounts();
});
</script>
@endif
@endsection
