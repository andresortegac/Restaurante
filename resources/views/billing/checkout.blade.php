@extends('layouts.app')

@section('title', 'Cobro ' . $order->order_number . ' - Facturacion')

@section('content')
@php
    $baseTotal = (float) $order->total;
    $initialIsCredit = (string) old('is_credit', '0') === '1';
    $initialReceived = $initialIsCredit ? 0.0 : (float) old('amount_received', $baseTotal);
    $initialChange = $initialIsCredit ? 0.0 : max(0, $initialReceived - $baseTotal);
    $initialDocumentType = old('document_type', 'ticket');
    $initialCustomerId = (string) old('customer_id', $order->customer_id);
    $currentCustomerName = $order->customer?->name ?: $order->customer_name;
    $defaultCashMethodId = $paymentMethods
        ->first(fn ($method) => strtoupper((string) $method->code) === 'CASH')
        ?->id;
    $initialPaymentMethodId = old('payment_method_id');
    $initialPaymentMethodId = filled($initialPaymentMethodId)
        ? (string) $initialPaymentMethodId
        : ($defaultCashMethodId ? (string) $defaultCashMethodId : '');
    $paymentMethodOptions = $paymentMethods->map(fn ($method) => [
        'id' => (int) $method->id,
        'name' => $method->name,
        'code' => strtoupper((string) $method->code),
    ])->values();
    $customerOptions = $customers->map(fn ($customer) => [
        'id' => (int) $customer->id,
        'name' => $customer->name,
        'document' => $customer->billing_identification ?: $customer->document_number,
        'email' => $customer->email,
        'phone' => $customer->phone,
        'pendingCreditTotal' => (float) ($customer->pending_credit_total ?? 0),
        'availableBalance' => (float) ($customer->available_balance ?? 0),
    ])->values();
@endphp

<div class="module-page">
    <section class="module-hero">
        <div>
            <span class="module-kicker">Caja / Facturacion</span>
            <h1>Cobro de {{ $order->order_number }}</h1>
        </div>
        <div class="summary-group">
            <span class="summary-chip">{{ $restaurantTable?->name ?? 'Mesa no disponible' }}</span>
            <span class="summary-chip">{{ $restaurantTable?->area ?: 'Salon principal' }}</span>
            <span class="summary-chip">Total ${{ number_format($baseTotal, 2) }}</span>
        </div>
    </section>

    @include('products.partials.form-errors')

    <style>
        .billing-checkout-layout {
            grid-template-columns: 1fr;
        }

        .billing-checkout-layout > aside {
            order: -1;
        }
    </style>

    <div class="table-detail-layout billing-checkout-layout">
        <div>
            <div class="card module-card service-card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h5 class="mb-1">Resumen del pedido</h5>
                        <p class="table-note mb-0">
                            Pedido abierto por {{ $order->openedBy?->name ?? 'el equipo' }}.
                            {{ $currentCustomerName ? 'Cliente actual: ' . $currentCustomerName . '.' : 'Sin cliente registrado.' }}
                        </p>
                    </div>
                    <a href="{{ route('billing.index') }}" class="btn btn-outline-secondary btn-sm">Volver a cuentas</a>
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
                                        <td>${{ number_format((float) $item->unit_price, 2) }}</td>
                                        <td class="text-end">${{ number_format((float) $item->subtotal, 2) }}</td>
                                    </tr>
                                @endforeach
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
                            <p class="mb-0">Abre una caja antes de cobrar este pedido.</p>
                        </div>
                    @else
                        <form method="POST" action="{{ route('billing.checkout.store', $order) }}" id="billingCheckoutForm" accept-charset="UTF-8">
                            @csrf

                            <div class="mb-3">
                                <label class="form-label" for="is_credit">Tipo de cobro</label>
                                <select class="form-select" id="is_credit" name="is_credit">
                                    <option value="0" @selected(! $initialIsCredit)>Pagado ahora</option>
                                    <option value="1" @selected($initialIsCredit)>Agregar a credito del cliente</option>
                                </select>
                                <div class="form-help mt-1" id="creditModeHelp"></div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="customer_id">Cliente del cobro</label>
                                <select class="form-select" id="customer_id" name="customer_id">
                                    <option value="">Consumidor final / sin cliente</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}" @selected($initialCustomerId === (string) $customer->id)>
                                            {{ $customer->name }}{{ $customer->document_number ? ' - ' . $customer->document_number : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-help mt-1" id="customerHelp"></div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="document_type">Documento a emitir</label>
                                <select class="form-select" id="document_type" name="document_type">
                                    <option value="ticket" @selected($initialDocumentType === 'ticket')>Ticket normal</option>
                                    <option value="electronic" @selected($initialDocumentType === 'electronic')>Factura electronica</option>
                                </select>
                                <div class="form-help mt-1" id="documentTypeHelp"></div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="payment_method_id">Metodo de pago</label>
                                <select class="form-select" id="payment_method_id" name="payment_method_id">
                                    <option value="">Selecciona un metodo</option>
                                    @foreach($paymentMethods as $paymentMethod)
                                        <option value="{{ $paymentMethod->id }}" data-payment-code="{{ strtoupper((string) $paymentMethod->code) }}" @selected($initialPaymentMethodId === (string) $paymentMethod->id)>
                                            {{ $paymentMethod->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="amount_received">Monto recibido</label>
                                <input type="number" class="form-control" id="amount_received" name="amount_received" min="0" step="0.01" value="{{ number_format($initialReceived, 2, '.', '') }}" required>
                                <div class="form-help mt-1" id="amountReceivedHelp"></div>
                            </div>

                            <div class="meta-box mb-3">
                                <div class="d-flex justify-content-between gap-3">
                                    <span class="summary-kicker">Total pedido</span>
                                    <strong>${{ number_format($baseTotal, 2) }}</strong>
                                </div>
                                <div class="d-flex justify-content-between gap-3 mt-2">
                                    <span class="summary-kicker">Total a cobrar</span>
                                    <strong id="checkoutAmountDue">${{ number_format($baseTotal, 2) }}</strong>
                                </div>
                                <div class="d-flex justify-content-between gap-3 mt-2">
                                    <span class="summary-kicker">Saldo a favor aplicado</span>
                                    <strong id="checkoutAppliedBalance">$0.00</strong>
                                </div>
                                <div class="d-flex justify-content-between gap-3 mt-2">
                                    <span class="summary-kicker">Cambio a devolver</span>
                                    <strong id="checkoutChange">${{ number_format($initialChange, 2) }}</strong>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-success w-100">Registrar cobro y liberar mesa</button>
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
    const customers = @json($customerOptions);
    const baseTotal = Number(@json($baseTotal));
    const defaultCashMethodId = @json($defaultCashMethodId ? (string) $defaultCashMethodId : '');
    const methodSelect = document.getElementById('payment_method_id');
    const amountReceivedInput = document.getElementById('amount_received');
    const amountDueLabel = document.getElementById('checkoutAmountDue');
    const appliedBalanceLabel = document.getElementById('checkoutAppliedBalance');
    const changeLabel = document.getElementById('checkoutChange');
    const amountReceivedHelp = document.getElementById('amountReceivedHelp');
    const creditMode = document.getElementById('is_credit');
    const creditModeHelp = document.getElementById('creditModeHelp');
    const customerSelect = document.getElementById('customer_id');
    const customerHelp = document.getElementById('customerHelp');
    const documentTypeSelect = document.getElementById('document_type');
    const documentTypeHelp = document.getElementById('documentTypeHelp');
    const checkoutForm = document.getElementById('billingCheckoutForm');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    if (!methodSelect || !amountReceivedInput || !amountDueLabel || !changeLabel || !checkoutForm || !documentTypeSelect || !creditMode || !customerSelect) {
        return;
    }

    const money = value => '$' + Number(value || 0).toLocaleString('es-CO', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    const selectedMethod = () => paymentMethods.find(method => String(method.id) === String(methodSelect.value)) || null;
    const selectedCustomer = () => customers.find(customer => String(customer.id) === String(customerSelect.value)) || null;
    const isCreditSale = () => String(creditMode.value || '0') === '1';
    const availableBalanceToApply = () => {
        const customer = selectedCustomer();

        if (!customer || isCreditSale()) {
            return 0;
        }

        return Math.min(Number(customer.availableBalance || 0), baseTotal);
    };
    const outstandingAmount = () => Math.max(0, baseTotal - availableBalanceToApply());
    const ensureDefaultCashMethod = () => {
        if (!methodSelect.value && defaultCashMethodId) {
            methodSelect.value = String(defaultCashMethodId);
        }
    };
    const isCashPayment = () => {
        const method = selectedMethod();
        return !method || method.code === 'CASH';
    };

    const syncCustomerHelp = () => {
        const customer = selectedCustomer();

        if (!customer) {
            customerHelp.textContent = isCreditSale()
                ? 'Selecciona un cliente para enviar esta cuenta a credito.'
                : 'Opcional para ticket normal y obligatorio para credito o factura electronica.';
            return;
        }

        const details = [
            customer.document ? 'Documento: ' + customer.document : null,
            customer.phone ? 'Telefono: ' + customer.phone : null,
            customer.email ? 'Email: ' + customer.email : null,
            'Saldo actual: ' + money(customer.pendingCreditTotal || 0),
            'Saldo a favor: ' + money(customer.availableBalance || 0),
        ].filter(Boolean);

        if (isCreditSale()) {
            details.push('Quedara en: ' + money((customer.pendingCreditTotal || 0) + baseTotal));
        } else if ((customer.availableBalance || 0) > 0) {
            details.push('Se aplicaran: ' + money(availableBalanceToApply()));
        }

        customerHelp.textContent = details.join(' | ');
    };

    const syncDocumentHelp = () => {
        if (documentTypeSelect.value !== 'electronic') {
            documentTypeHelp.textContent = 'Se generara un ticket local para este cobro.';
            return;
        }

        documentTypeHelp.textContent = selectedCustomer()
            ? 'Se intentara emitir factura electronica para el cliente seleccionado.'
            : 'Para factura electronica selecciona un cliente registrado con datos completos.';
    };

    const syncAmounts = () => {
        if (!isCreditSale()) {
            ensureDefaultCashMethod();
        }

        const method = selectedMethod();
        const cashPayment = isCashPayment();
        const requiresExactAmount = Boolean(method) && !cashPayment;
        const appliedBalance = availableBalanceToApply();
        const amountDue = outstandingAmount();
        const submitButton = checkoutForm.querySelector('button[type="submit"]');

        if (isCreditSale()) {
            methodSelect.value = '';
            methodSelect.disabled = true;
            amountReceivedInput.value = '0.00';
            amountReceivedInput.readOnly = true;
            amountReceivedInput.classList.add('bg-light');
            amountReceivedHelp.textContent = 'Esta cuenta quedara pendiente y no registrara ingreso en caja por ahora.';

            if (submitButton) {
                submitButton.textContent = 'Enviar a credito y liberar mesa';
            }
        } else if (amountDue <= 0) {
            methodSelect.value = '';
            methodSelect.disabled = true;
            amountReceivedInput.value = '0.00';
            amountReceivedInput.readOnly = true;
            amountReceivedInput.classList.add('bg-light');
            amountReceivedHelp.textContent = 'El saldo a favor cubre toda la cuenta. No entra dinero a caja.';

            if (submitButton) {
                submitButton.textContent = 'Aplicar saldo a favor y liberar mesa';
            }
        } else if (requiresExactAmount) {
            methodSelect.disabled = false;
            amountReceivedInput.value = amountDue.toFixed(2);
            amountReceivedInput.readOnly = true;
            amountReceivedInput.classList.add('bg-light');
            amountReceivedHelp.textContent = appliedBalance > 0
                ? 'Primero se descuenta el saldo a favor y el resto debe coincidir exactamente con el total.'
                : 'Para pagos distintos a efectivo, el monto recibido debe coincidir con el total.';

            if (submitButton) {
                submitButton.textContent = 'Registrar cobro y liberar mesa';
            }
        } else {
            methodSelect.disabled = false;

            if (amountReceivedInput.dataset.userEdited !== 'true' || Number(amountReceivedInput.value || 0) < amountDue) {
                amountReceivedInput.value = amountDue.toFixed(2);
            }

            amountReceivedInput.readOnly = false;
            amountReceivedInput.classList.remove('bg-light');
            amountReceivedHelp.textContent = appliedBalance > 0
                ? 'El sistema descuenta primero el saldo a favor y te pide solo el valor restante.'
                : (method
                    ? 'Ingresa el valor recibido y el sistema calculara el cambio.'
                    : 'Selecciona un metodo de pago para continuar.');

            if (submitButton) {
                submitButton.textContent = 'Registrar cobro y liberar mesa';
            }
        }

        const receivedAmount = Math.max(0, Number(amountReceivedInput.value || 0));
        const changeAmount = !isCreditSale() && cashPayment ? Math.max(0, receivedAmount - amountDue) : 0;

        amountDueLabel.textContent = money(isCreditSale() ? baseTotal : amountDue);
        appliedBalanceLabel.textContent = money(isCreditSale() ? 0 : appliedBalance);
        changeLabel.textContent = money(changeAmount);
        creditModeHelp.textContent = isCreditSale()
            ? 'El cliente quedara vinculado desde este formulario.'
            : (appliedBalance > 0
                ? 'Si el cliente tiene saldo a favor, se descuenta antes de registrar el cobro restante.'
                : 'El cobro se registrara de inmediato en la venta.');
        customerSelect.required = isCreditSale() || documentTypeSelect.value === 'electronic';
        syncCustomerHelp();
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

    creditMode.addEventListener('change', syncAmounts);
    customerSelect.addEventListener('change', function () {
        amountReceivedInput.dataset.userEdited = 'false';
        syncCustomerHelp();
        syncDocumentHelp();
        syncAmounts();
    });
    documentTypeSelect.addEventListener('change', function () {
        syncDocumentHelp();
        syncAmounts();
    });

    checkoutForm.addEventListener('submit', async function (event) {
        event.preventDefault();

        if (isCreditSale() && !selectedCustomer()) {
            if (window.Swal) {
                await Swal.fire({
                    icon: 'warning',
                    title: 'Falta el cliente',
                    text: 'Selecciona un cliente antes de enviar la cuenta a credito.',
                    confirmButtonText: 'Aceptar',
                    confirmButtonColor: '#2563eb',
                });
            } else {
                alert('Selecciona un cliente antes de enviar la cuenta a credito.');
            }

            return;
        }

        if (documentTypeSelect.value === 'electronic' && !selectedCustomer()) {
            if (window.Swal) {
                await Swal.fire({
                    icon: 'warning',
                    title: 'Falta el cliente',
                    text: 'Selecciona un cliente registrado antes de emitir factura electronica.',
                    confirmButtonText: 'Aceptar',
                    confirmButtonColor: '#2563eb',
                });
            } else {
                alert('Selecciona un cliente registrado antes de emitir factura electronica.');
            }

            return;
        }

        const submitButton = checkoutForm.querySelector('button[type="submit"]');
        const originalLabel = submitButton ? submitButton.innerHTML : '';

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registrando cobro...';
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
                        title: 'No se pudo cobrar la mesa',
                        text: validationMessages,
                        confirmButtonText: 'Aceptar',
                        confirmButtonColor: '#dc3545',
                    });
                } else {
                    alert(validationMessages);
                }

                return;
            }

            if (window.Swal) {
                await Swal.fire({
                    icon: data?.cufe ? 'success' : 'info',
                    title: data?.cufe ? 'Cobro y factura listos' : 'Cobro registrado',
                    text: data?.cufe
                        ? 'CUFE generado: ' + data.cufe
                        : (data?.message || 'El documento se esta preparando para impresion.'),
                    confirmButtonText: 'Continuar',
                    confirmButtonColor: '#2563eb',
                });
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

    syncDocumentHelp();
    syncCustomerHelp();
    ensureDefaultCashMethod();
    syncAmounts();
});
</script>
@endif
@endsection
