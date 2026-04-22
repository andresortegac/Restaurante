@extends('layouts.app')

@section('title', 'Cobro ' . $order->order_number . ' - RestaurantePOS')

@section('content')
@php
    $baseTotal = (float) $order->total;
    $initialTip = (float) old('tip_amount', 0);
    $initialDue = $baseTotal + $initialTip;
    $initialReceived = (float) old('amount_received', $initialDue);
    $initialChange = max(0, $initialReceived - $initialDue);
    $paymentMethodOptions = $paymentMethods->map(fn ($method) => [
        'id' => (int) $method->id,
        'name' => $method->name,
        'code' => strtoupper((string) $method->code),
    ])->values();
@endphp

<div class="module-page">
    <section class="module-hero">
        <div>
            <span class="module-kicker">Cobro de pedidos</span>
            <h1>Checkout de {{ $order->order_number }}</h1>
            <p>Registra el pago de la mesa, crea la venta en el POS y libera la mesa solo cuando el cobro quede guardado.</p>
        </div>
        <div class="summary-group">
            <span class="summary-chip">{{ $restaurantTable?->name ?? 'Mesa no disponible' }}</span>
            <span class="summary-chip">{{ $restaurantTable?->area ?: 'Salon principal' }}</span>
            <span class="summary-chip">Total base ${{ number_format($baseTotal, 2) }}</span>
        </div>
    </section>

    @include('products.partials.form-errors')

    <div class="table-detail-layout">
        <div>
            <div class="card module-card service-card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h5 class="mb-1">Resumen del pedido</h5>
                        <p class="table-note mb-0">
                            Pedido abierto por {{ $order->openedBy?->name ?? 'el equipo' }}.
                            {{ $order->customer?->name || $order->customer_name ? 'Cliente: ' . ($order->customer?->name ?: $order->customer_name) . '.' : 'Sin cliente registrado.' }}
                        </p>
                    </div>
                    <a href="{{ route('orders.show', $restaurantTable) }}" class="btn btn-outline-secondary btn-sm">Volver al pedido</a>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="order-summary-card h-100">
                                <div class="summary-kicker">Subtotal</div>
                                <div class="h3 mb-0">${{ number_format((float) $order->subtotal, 2) }}</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="order-summary-card h-100">
                                <div class="summary-kicker">Impuesto</div>
                                <div class="h3 mb-0">${{ number_format((float) $order->tax_amount, 2) }}</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="order-summary-card h-100">
                                <div class="summary-kicker">Total del pedido</div>
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
                                    <th>Cuenta</th>
                                    <th>Precio</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($order->items as $item)
                                    <tr>
                                        <td>
                                            <strong>{{ $item->product_name }}</strong>
                                            <div class="table-note">{{ $item->notes ?: 'Sin observaciones' }}</div>
                                        </td>
                                        <td>{{ $item->quantity }}</td>
                                        <td>Cuenta {{ $item->split_group ?: 1 }}</td>
                                        <td>${{ number_format((float) $item->unit_price, 2) }}</td>
                                        <td class="text-end">${{ number_format((float) $item->subtotal, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($splitSummary->isNotEmpty())
                        <div class="mt-4">
                            <div class="summary-kicker mb-2">Division de cuentas guardada</div>
                            <div class="row g-3">
                                @foreach($splitSummary as $group)
                                    <div class="col-md-6 col-xl-4">
                                        <div class="meta-box h-100">
                                            <div class="fw-bold">Cuenta {{ $group['group'] }}</div>
                                            <div class="seat-note">{{ $group['items_count'] }} item(s)</div>
                                            <div class="seat-note">Subtotal ${{ number_format((float) $group['subtotal'], 2) }}</div>
                                            <div class="seat-note">Impuesto ${{ number_format((float) $group['tax_amount'], 2) }}</div>
                                            <div class="fw-bold mt-2">Total ${{ number_format((float) $group['total'], 2) }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
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
                            <p class="mb-0">Abre una caja desde el POS antes de cobrar este pedido.</p>
                        </div>
                    @else
                        <div class="meta-box mb-3">
                            <div class="summary-kicker">Caja activa</div>
                            <div class="fw-bold">{{ $activeBox->name }}</div>
                            <div class="seat-note">Codigo {{ $activeBox->code }}</div>
                            <div class="seat-note">Saldo estimado: ${{ number_format($activeBox->currentBalance(), 2) }}</div>
                        </div>

                        <form method="POST" action="{{ route('orders.checkout.store', $order) }}" id="orderCheckoutForm" accept-charset="UTF-8">
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
                                <label class="form-label" for="tip_amount">Propina</label>
                                <input type="number" class="form-control" id="tip_amount" name="tip_amount" min="0" step="0.01" value="{{ number_format($initialTip, 2, '.', '') }}">
                                <div class="form-help mt-1">La propina se guarda separada de la venta para el historial del cobro.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="amount_received">Monto recibido</label>
                                <input type="number" class="form-control" id="amount_received" name="amount_received" min="0" step="0.01" value="{{ number_format($initialReceived, 2, '.', '') }}" required>
                                <div class="form-help mt-1" id="amountReceivedHelp">Ingresa el valor recibido y veras de inmediato el cambio a devolver.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="reference">Referencia</label>
                                <input type="text" class="form-control" id="reference" name="reference" value="{{ old('reference') }}" placeholder="Últimos dígitos, comprobante o nota del pago" lang="es" autocomplete="on" autocapitalize="sentences" inputmode="text" spellcheck="false">
                            </div>

                            <div class="meta-box mb-3">
                                <div class="d-flex justify-content-between gap-3">
                                    <span class="summary-kicker">Total pedido</span>
                                    <strong>${{ number_format($baseTotal, 2) }}</strong>
                                </div>
                                <div class="d-flex justify-content-between gap-3 mt-2">
                                    <span class="summary-kicker">Total a cobrar</span>
                                    <strong id="checkoutAmountDue">${{ number_format($initialDue, 2) }}</strong>
                                </div>
                                <div class="d-flex justify-content-between gap-3 mt-2">
                                    <span class="summary-kicker">Cambio a devolver</span>
                                    <strong id="checkoutChange">${{ number_format($initialChange, 2) }}</strong>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-success w-100">Registrar pago y liberar mesa</button>
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
    const tipInput = document.getElementById('tip_amount');
    const amountReceivedInput = document.getElementById('amount_received');
    const amountDueLabel = document.getElementById('checkoutAmountDue');
    const changeLabel = document.getElementById('checkoutChange');
    const amountReceivedHelp = document.getElementById('amountReceivedHelp');
    const checkoutForm = document.getElementById('orderCheckoutForm');
    const referenceInput = document.getElementById('reference');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    if (referenceInput) {
        referenceInput.placeholder = 'Ultimos digitos, comprobante o nota del pago';
    }

    if (!methodSelect || !tipInput || !amountReceivedInput || !amountDueLabel || !changeLabel || !checkoutForm) {
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
        const tipAmount = Math.max(0, Number(tipInput.value || 0));
        const amountDue = baseTotal + tipAmount;
        const method = selectedMethod();
        const cashPayment = isCashPayment();
        const requiresExactAmount = Boolean(method) && !cashPayment;

        if (requiresExactAmount) {
            amountReceivedInput.value = amountDue.toFixed(2);
            amountReceivedInput.readOnly = true;
            amountReceivedInput.classList.add('bg-light');
            amountReceivedHelp.textContent = 'Para pagos distintos a efectivo, el monto recibido debe coincidir con el total a cobrar.';
        } else {
            const currentValue = Math.max(0, Number(amountReceivedInput.value || 0));
            if (currentValue < amountDue || amountReceivedInput.dataset.userEdited !== 'true') {
                amountReceivedInput.value = amountDue.toFixed(2);
            }

            amountReceivedInput.readOnly = false;
            amountReceivedInput.classList.remove('bg-light');
            amountReceivedHelp.textContent = method
                ? 'Ingresa el valor recibido y veras de inmediato el cambio a devolver.'
                : 'Ingresa el valor recibido y el sistema te mostrara el cambio al instante.';
        }

        const receivedAmount = Math.max(0, Number(amountReceivedInput.value || 0));
        const changeAmount = cashPayment ? Math.max(0, receivedAmount - amountDue) : 0;

        amountDueLabel.textContent = money(amountDue);
        changeLabel.textContent = money(changeAmount);
    };

    amountReceivedInput.addEventListener('input', function () {
        amountReceivedInput.dataset.userEdited = 'true';
        syncAmounts();
    });

    tipInput.addEventListener('input', syncAmounts);
    methodSelect.addEventListener('change', function () {
        if (!isCashPayment()) {
            amountReceivedInput.dataset.userEdited = 'false';
        }

        syncAmounts();
    });

    checkoutForm.addEventListener('submit', async function (event) {
        event.preventDefault();

        const submitButton = checkoutForm.querySelector('button[type="submit"]');
        const originalLabel = submitButton ? submitButton.innerHTML : '';

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cerrando mesa...';
        }

        try {
            const response = await fetch(checkoutForm.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: new FormData(checkoutForm),
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                const validationMessages = data?.errors
                    ? Object.values(data.errors).flat().join('\n')
                    : (data?.message || 'No se pudo registrar el cobro.');

                if (window.Swal) {
                    await Swal.fire({
                        icon: 'error',
                        title: 'No se pudo cerrar la mesa',
                        text: validationMessages,
                        confirmButtonText: 'Aceptar',
                        confirmButtonColor: '#dc3545',
                    });
                } else {
                    alert(validationMessages);
                }

                return;
            }

            window.location.href = data?.printUrl || checkoutForm.action;
        } catch (error) {
            if (window.Swal) {
                await Swal.fire({
                    icon: 'error',
                    title: 'Error inesperado',
                    text: 'No se pudo registrar el cobro. Intenta nuevamente.',
                    confirmButtonText: 'Aceptar',
                    confirmButtonColor: '#dc3545',
                });
            } else {
                alert('No se pudo registrar el cobro. Intenta nuevamente.');
            }
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.innerHTML = originalLabel;
            }
        }
    });

    syncAmounts();
});
</script>
@endif
@endsection
