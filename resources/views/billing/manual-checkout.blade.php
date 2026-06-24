@extends('layouts.app')

@section('title', 'Cobro manual - Facturacion')

@section('content')
@php
    $initialDocumentType = old('document_type', 'ticket');
    $initialIsCredit = (string) old('is_credit', '0') === '1';
    $oldPaymentMode = old('payment_mode', $initialIsCredit ? 'customer_balance_credit' : 'paid_now');
    $initialPaymentMode = in_array($oldPaymentMode, ['customer_balance', 'credit'], true)
        ? 'customer_balance_credit'
        : $oldPaymentMode;
    $oldItems = collect(old('items', [['product_id' => '', 'name' => '', 'quantity' => 1, 'unit_price' => 0]]))->values();
    $paymentMethodOptions = $paymentMethods->map(fn ($method) => [
        'id' => (int) $method->id,
        'name' => $method->name,
        'code' => strtoupper((string) $method->code),
    ])->values();
    $productOptions = $products->map(fn ($product) => [
        'id' => (int) $product->id,
        'name' => $product->name,
        'description' => $product->description,
        'price' => (float) $product->price,
        'type' => $product->product_type ?: 'simple',
        'imageUrl' => $product->image_url,
        'categoryId' => $product->category_id ? 'category-' . $product->category_id : 'uncategorized',
        'categoryName' => $product->menuCategory->name ?? 'Sin categoria',
        'searchText' => \Illuminate\Support\Str::lower(trim(implode(' ', [
            $product->name,
            $product->description,
            $product->menuCategory->name ?? 'Sin categoria',
            $product->product_type ?: 'simple',
        ]))),
    ])->values();
    $menuCategories = $products
        ->groupBy(fn ($product) => $product->category_id ? 'category-' . $product->category_id : 'uncategorized')
        ->map(function ($products, $categoryKey) {
            $firstProduct = $products->first();

            return [
                'id' => $categoryKey,
                'name' => $firstProduct->menuCategory->name ?? 'Sin categoria',
                'description' => $firstProduct->menuCategory->description ?? 'Productos listos para agregar al cobro.',
                'count' => $products->count(),
            ];
        })
        ->values();
    $customerOptions = $customers->map(fn ($customer) => [
        'id' => (int) $customer->id,
        'name' => $customer->name,
        'document' => $customer->billing_identification ?: $customer->document_number,
        'email' => $customer->email,
        'phone' => $customer->phone,
        'address' => $customer->billing_address,
        'pendingCreditTotal' => (float) ($customer->pending_credit_total ?? 0),
        'availableBalance' => (float) ($customer->available_balance ?? 0),
    ])->values();
@endphp

<div class="module-page">
    <section class="module-hero">
        <div>
            <span class="module-kicker">Caja / Facturacion</span>
            <h1>Cobro manual</h1>
        </div>
        <div class="summary-group">
            <span class="summary-chip">Mesa por defecto</span>
            <span class="summary-chip">Ticket o factura electronica</span>
            <span class="summary-chip">Pago inmediato o credito</span>
        </div>
    </section>

    @include('products.partials.form-errors')

    <form method="POST" action="{{ route('billing.manual.store') }}" id="manualBillingForm" accept-charset="UTF-8">
        @csrf
        <input type="hidden" name="origin_type" value="delivery">

        <div class="billing-manual-stack">
            <div class="card module-card service-card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <h5 class="mb-0">Registrar pago</h5>
                    <a href="{{ route('billing.history') }}" class="btn btn-outline-secondary btn-sm">Ver ventas</a>
                </div>
                <div class="card-body">
                    @if(!$activeBox)
                        <div class="empty-state py-4">
                            <i class="fas fa-cash-register"></i>
                            <h5 class="mb-2">No hay caja abierta</h5>
                            <p class="mb-0">Abre una caja antes de registrar cobros manuales.</p>
                        </div>
                    @else
                        <div class="manual-payment-grid">
                            <div>
                                <label class="form-label" for="document_type">Documento a emitir</label>
                                <select class="form-select" id="document_type" name="document_type">
                                    <option value="ticket" @selected($initialDocumentType === 'ticket')>Ticket normal</option>
                                    <option value="electronic" @selected($initialDocumentType === 'electronic')>Factura electronica</option>
                                </select>
                                <div class="form-help mt-1" id="documentTypeHelp"></div>
                            </div>

                            <div>
                                <label class="form-label" for="is_credit">Tipo de cobro</label>
                                <select class="form-select" id="is_credit" name="payment_mode">
                                    <option value="paid_now" @selected($initialPaymentMode === 'paid_now')>Pagado ahora</option>
                                    <option value="customer_balance_credit" @selected($initialPaymentMode === 'customer_balance_credit')>Saldo a favor / credito al cliente</option>
                                </select>
                                <div class="form-help mt-1">Pagado ahora no descuenta saldo. El modo combinado usa saldo a favor y deja el restante a credito.</div>
                            </div>

                            <div>
                                <label class="form-label" for="customer_id">Cliente registrado</label>
                                <select class="form-select" id="customer_id" name="customer_id">
                                    <option value="">Consumidor final / sin cliente</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}" @selected((int) old('customer_id') === (int) $customer->id)>
                                            {{ $customer->name }}{{ $customer->document_number ? ' - ' . $customer->document_number : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-help mt-1" id="customerFieldHelp">Opcional para tickets pagados ahora.</div>
                            </div>

                            <div>
                                <label class="form-label" for="payment_method_id">Metodo de pago</label>
                                <select class="form-select" id="payment_method_id" name="payment_method_id">
                                    <option value="" @selected(! old('payment_method_id'))>Sin dato</option>
                                    @foreach($paymentMethods as $paymentMethod)
                                        <option value="{{ $paymentMethod->id }}" data-payment-code="{{ strtoupper((string) $paymentMethod->code) }}" @selected((int) old('payment_method_id') === (int) $paymentMethod->id)>
                                            {{ $paymentMethod->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-help mt-1">Puedes dejarlo en blanco; se guardara como sin dato.</div>
                            </div>

                            <div>
                                <label class="form-label" for="amount_received">Monto recibido</label>
                                <input type="number" class="form-control" id="amount_received" name="amount_received" min="0" step="1" value="{{ money_input(old('amount_received', 0)) }}" required>
                                <div class="form-help mt-1" id="amountReceivedHelp">Ingresa el valor recibido para calcular el cambio.</div>
                            </div>

                            <div class="meta-box manual-payment-totals">
                                <div class="mb-3">
                                    <span class="summary-kicker d-block">Cliente</span>
                                    <strong id="manualCustomerLabel">Consumidor final / sin cliente</strong>
                                    <div class="text-muted small" id="manualCustomerMeta">Selecciona un cliente si quieres asociar el cobro.</div>
                                </div>
                                <div class="d-flex justify-content-between gap-3">
                                    <span class="summary-kicker">Subtotal</span>
                                    <strong id="manualSubtotal">$0</strong>
                                </div>
                                <div class="d-flex justify-content-between gap-3 mt-2">
                                    <span class="summary-kicker">Total a cobrar</span>
                                    <strong id="manualAmountDue">$0</strong>
                                </div>
                                <div class="d-flex justify-content-between gap-3 mt-2">
                                    <span class="summary-kicker">Saldo a favor aplicado</span>
                                    <strong id="manualAppliedBalance">$0</strong>
                                </div>
                                <div class="d-flex justify-content-between gap-3 mt-2">
                                    <span class="summary-kicker">Cambio</span>
                                    <strong id="manualChange">$0</strong>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions manual-payment-actions">
                            <button type="submit" class="btn btn-success">Registrar cobro manual</button>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card module-card service-card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h5 class="mb-1">Conceptos a cobrar</h5>
                        <p class="table-note mb-0">Selecciona productos del menu para agregarlos al cobro.</p>
                    </div>
                    <span class="summary-chip" id="manualUniqueItemsChip">0 referencias</span>
                </div>
                <div class="card-body">
                    @if($products->isEmpty())
                        <div class="empty-state py-4">
                            <i class="fas fa-utensils"></i>
                            <h5 class="mb-2">No hay productos disponibles</h5>
                            <p class="mb-0">Activa productos del menu para poder cobrarlos manualmente.</p>
                        </div>
                    @else
                        <aside class="waiter-draft-shell order-draft-before-products mb-4">
                            <div class="waiter-draft-header">
                                <div>
                                    <div class="summary-kicker">Resumen del cobro</div>
                                    <h6 class="mb-1">Productos seleccionados</h6>
                                    <p class="table-note mb-0">Ajusta cantidades antes de registrar el pago.</p>
                                </div>
                                <span class="summary-chip" id="manualUnitsCount">0 unidades</span>
                            </div>

                            <div class="waiter-empty-draft meta-box" id="manualEmptyDraft">
                                Toca los productos del menu para agregarlos al cobro.
                            </div>

                            <div class="waiter-draft-items waiter-draft-items-manual" id="manualSelectedItems"></div>
                            <div id="manualItemsInputs"></div>
                        </aside>

                        <div class="waiter-menu-shell">
                            <div class="waiter-menu-toolbar">
                                <div>
                                    <div class="summary-kicker">Carta del menu</div>
                                    <h6 class="mb-1">Selecciona productos para el cobro</h6>
                                    <p class="table-note mb-0">Toca un producto para sumarlo al cobro manual.</p>
                                </div>
                                <div class="waiter-menu-search waiter-menu-search-emphasis">
                                    <label class="form-label" for="manualProductSearch">Filtrar por nombre</label>
                                    <input type="search" class="form-control" id="manualProductSearch" placeholder="Ej: churrasco, limonada, postre">
                                </div>
                            </div>

                            <div class="waiter-menu-groups" id="manualProductGrid"></div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </form>
</div>

@if($activeBox)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const products = @json($productOptions);
    const categories = @json($menuCategories);
    const oldItems = @json($oldItems);
    const customers = @json($customerOptions);
    const paymentMethods = @json($paymentMethodOptions);
    const form = document.getElementById('manualBillingForm');
    const productGrid = document.getElementById('manualProductGrid');
    const productSearch = document.getElementById('manualProductSearch');
    const selectedItemsContainer = document.getElementById('manualSelectedItems');
    const selectedInputsContainer = document.getElementById('manualItemsInputs');
    const emptyDraftState = document.getElementById('manualEmptyDraft');
    const unitsCount = document.getElementById('manualUnitsCount');
    const uniqueItemsChip = document.getElementById('manualUniqueItemsChip');
    const paymentMethod = document.getElementById('payment_method_id');
    const amountReceived = document.getElementById('amount_received');
    const amountHelp = document.getElementById('amountReceivedHelp');
    const creditMode = document.getElementById('is_credit');
    const subtotalLabel = document.getElementById('manualSubtotal');
    const dueLabel = document.getElementById('manualAmountDue');
    const appliedBalanceLabel = document.getElementById('manualAppliedBalance');
    const changeLabel = document.getElementById('manualChange');
    const documentType = document.getElementById('document_type');
    const documentHelp = document.getElementById('documentTypeHelp');
    const customerSelect = document.getElementById('customer_id');
    const customerFieldHelp = document.getElementById('customerFieldHelp');
    const customerLabel = document.getElementById('manualCustomerLabel');
    const customerMeta = document.getElementById('manualCustomerMeta');
    const selectedItems = new Map();
    let activeCategory = 'all';

    const money = value => '$' + Math.round(Number(value || 0)).toLocaleString('es-CO');
    const moneyInput = value => String(Math.max(0, Math.round(Number(value || 0))));
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[char]));

    const selectedMethod = () => paymentMethods.find(method => String(method.id) === String(paymentMethod.value)) || null;
    const paymentMode = () => String(creditMode?.value || 'paid_now');
    const isBalanceCreditMode = () => paymentMode() === 'customer_balance_credit';
    const creditBalanceRemainder = amount => {
        if (!isBalanceCreditMode()) {
            return 0;
        }

        return Math.max(0, Number(amount || 0) - availableBalanceToApply(amount));
    };
    const isCreditSale = () => isBalanceCreditMode() && creditBalanceRemainder(calculateSubtotal()) > 0;
    const isCashPayment = () => {
        const method = selectedMethod();
        return !method || method.code === 'CASH';
    };
    const calculateSubtotal = () => Array.from(selectedItems.values())
        .reduce((sum, entry) => sum + (entry.quantity * entry.unitPrice), 0);
    const shouldApplyCustomerBalance = () => isBalanceCreditMode();
    const availableBalanceToApply = amount => {
        const customer = selectedCustomer();

        if (!customer || !shouldApplyCustomerBalance()) {
            return 0;
        }

        return Math.min(Number(customer.availableBalance || 0), Math.max(0, Number(amount || 0)));
    };
    const selectedCustomer = () => customers.find(customer => String(customer.id) === String(customerSelect.value)) || null;
    const findProduct = productId => products.find(product => String(product.id) === String(productId)) || null;
    const placeholderMarkup = icon => '<div class="waiter-image-placeholder"><i class="' + icon + '"></i></div>';
    const requiresRegisteredCustomer = () => isBalanceCreditMode() || documentType.value === 'electronic';

    const syncCustomerSummary = (projectedAmount = null, appliedBalance = 0) => {
        const customer = selectedCustomer();

        if (customer) {
            customerLabel.textContent = customer.name;
            const details = [
                customer.document ? 'Documento: ' + customer.document : null,
                customer.phone ? 'Telefono: ' + customer.phone : null,
                customer.email ? 'Email: ' + customer.email : null,
                'Saldo a favor: ' + money(customer.availableBalance || 0),
            ].filter(Boolean);

            if (customer.pendingCreditTotal > 0) {
                details.push('Cartera actual: ' + money(customer.pendingCreditTotal));
            }

            const creditRemainder = creditBalanceRemainder(projectedAmount || 0);

            if (creditRemainder > 0) {
                details.push('Quedara en cartera: ' + money(customer.pendingCreditTotal + creditRemainder));
            } else if (shouldApplyCustomerBalance() && appliedBalance > 0) {
                details.push('Se aplicaran: ' + money(appliedBalance));
            }

            customerMeta.textContent = details.join(' | ') || 'Cliente registrado.';
            return;
        }

        customerLabel.textContent = 'Consumidor final / sin cliente';
        customerMeta.textContent = requiresRegisteredCustomer()
            ? 'Selecciona un cliente creado para continuar con este cobro.'
            : 'Selecciona un cliente si quieres asociar el cobro.';
    };

    const syncCustomerFieldState = () => {
        if (!customerFieldHelp) {
            return;
        }

        customerSelect.required = requiresRegisteredCustomer() || shouldApplyCustomerBalance();

        if (isBalanceCreditMode()) {
            customerFieldHelp.textContent = 'Obligatorio para usar saldo a favor y dejar restante a credito.';
            return;
        }

        if (documentType.value === 'electronic') {
            customerFieldHelp.textContent = 'Obligatorio para emitir factura electronica.';
            return;
        }

        customerFieldHelp.textContent = shouldApplyCustomerBalance()
            ? 'Obligatorio para descontar saldo a favor.'
            : 'Opcional para tickets pagados ahora.';
    };

    const syncDocumentHelp = () => {
        if (documentType.value !== 'electronic') {
            documentHelp.textContent = 'El ticket se imprime de inmediato.';
            return;
        }

        const customer = selectedCustomer();
        documentHelp.textContent = customer
            ? 'Se intentara emitir factura electronica para el cliente seleccionado.'
            : 'Para factura electronica selecciona un cliente registrado con datos de facturacion completos.';
    };

    const syncTotals = () => {
        const entries = Array.from(selectedItems.values());
        const subtotal = calculateSubtotal();
        const appliedBalance = availableBalanceToApply(subtotal);
        const due = Math.max(0, subtotal - appliedBalance);
        const creditRemainder = creditBalanceRemainder(subtotal);
        const method = selectedMethod();
        const exactPayment = Boolean(method) && !isCashPayment();
        const submitButton = form.querySelector('button[type="submit"]');

        if (isBalanceCreditMode()) {
            paymentMethod.value = '';
            paymentMethod.disabled = true;
            amountReceived.value = '0';
            amountReceived.readOnly = true;
            amountReceived.classList.add('bg-light');
            amountHelp.textContent = creditRemainder > 0
                ? 'Se descuenta el saldo a favor disponible y el restante queda a credito.'
                : 'El saldo a favor cubre todo el cobro. No se registra ingreso en caja.';
            if (submitButton) {
                submitButton.textContent = creditRemainder > 0
                    ? 'Aplicar saldo y dejar credito'
                    : 'Aplicar saldo a favor';
            }
        } else if (due <= 0 && subtotal > 0) {
            paymentMethod.value = '';
            paymentMethod.disabled = true;
            amountReceived.value = '0';
            amountReceived.readOnly = true;
            amountReceived.classList.add('bg-light');
            amountHelp.textContent = 'El saldo a favor cubre todo el cobro. No se registra ingreso en caja.';
            if (submitButton) {
                submitButton.textContent = 'Aplicar saldo a favor';
            }
        } else if (exactPayment) {
            paymentMethod.disabled = false;
            amountReceived.value = moneyInput(due);
            amountReceived.readOnly = true;
            amountReceived.classList.add('bg-light');
            amountHelp.textContent = appliedBalance > 0
                ? 'Primero se descuenta el saldo a favor y el resto debe coincidir exactamente con el total.'
                : 'Para pagos distintos a efectivo, el monto recibido debe coincidir con el total.';
            if (submitButton) {
                submitButton.textContent = 'Registrar cobro manual';
            }
        } else {
            paymentMethod.disabled = false;
            amountReceived.readOnly = false;
            amountReceived.classList.remove('bg-light');
            amountHelp.textContent = appliedBalance > 0
                ? 'El sistema descuenta primero el saldo a favor y te pide solo el valor restante.'
                : 'Ingresa el valor recibido para calcular el cambio.';

            if (amountReceived.dataset.userEdited !== 'true' && due > 0) {
                amountReceived.value = moneyInput(due);
            }

            if (submitButton) {
                submitButton.textContent = 'Registrar cobro manual';
            }
        }

        const received = Math.max(0, Number(amountReceived.value || 0));
        subtotalLabel.textContent = money(subtotal);
        dueLabel.textContent = money(isBalanceCreditMode() ? creditRemainder : due);
        appliedBalanceLabel.textContent = money(appliedBalance);
        changeLabel.textContent = money(!isBalanceCreditMode() && isCashPayment() ? Math.max(0, received - due) : 0);
        syncCustomerSummary(subtotal, appliedBalance);
    };

    const syncSelectedItems = () => {
        const entries = Array.from(selectedItems.values());
        const units = entries.reduce((sum, entry) => sum + entry.quantity, 0);

        if (!selectedItemsContainer || !selectedInputsContainer) {
            syncTotals();
            return;
        }

        emptyDraftState.style.display = entries.length ? 'none' : '';
        unitsCount.textContent = units + (units === 1 ? ' unidad' : ' unidades');
        uniqueItemsChip.textContent = entries.length + (entries.length === 1 ? ' referencia' : ' referencias');

        selectedItemsContainer.innerHTML = entries.map(entry => {
            const mediaMarkup = entry.product.imageUrl
                ? '<img src="' + escapeHtml(entry.product.imageUrl) + '" alt="' + escapeHtml(entry.product.name) + '" loading="lazy" decoding="async">'
                : placeholderMarkup('fas fa-image');

            return (
                '<div class="waiter-draft-item" data-selected-product="' + entry.product.id + '">' +
                    '<div class="waiter-draft-thumb">' + mediaMarkup + '</div>' +
                    '<div class="waiter-draft-copy">' +
                        '<h6 class="mb-1">' + escapeHtml(entry.product.name) + '</h6>' +
                        '<div class="table-note">' + escapeHtml(entry.product.categoryName) + '</div>' +
                        '<div class="waiter-draft-controls">' +
                            '<div class="waiter-qty-controls">' +
                                '<button type="button" class="btn btn-outline-secondary btn-sm" data-decrease-product="' + entry.product.id + '"><i class="fas fa-minus"></i></button>' +
                                '<span>' + entry.quantity + '</span>' +
                                '<button type="button" class="btn btn-outline-secondary btn-sm" data-increase-product="' + entry.product.id + '"><i class="fas fa-plus"></i></button>' +
                            '</div>' +
                            '<strong>' + money(entry.quantity * entry.unitPrice) + '</strong>' +
                        '</div>' +
                        '<button type="button" class="btn btn-outline-danger btn-sm mt-2" data-remove-product="' + entry.product.id + '"><i class="fas fa-trash"></i> Quitar</button>' +
                    '</div>' +
                '</div>'
            );
        }).join('');

        selectedInputsContainer.innerHTML = entries.map((entry, index) => (
            '<input type="hidden" name="items[' + index + '][product_id]" value="' + entry.product.id + '">' +
            '<input type="hidden" name="items[' + index + '][name]" value="' + escapeHtml(entry.product.name) + '">' +
            '<input type="hidden" name="items[' + index + '][quantity]" value="' + entry.quantity + '">' +
            '<input type="hidden" name="items[' + index + '][unit_price]" value="' + moneyInput(entry.unitPrice) + '">'
        )).join('');

        syncTotals();
    };

    const addProduct = product => {
        if (!product) {
            return;
        }

        const existing = selectedItems.get(String(product.id));
        selectedItems.set(String(product.id), {
            product,
            quantity: existing ? existing.quantity + 1 : 1,
            unitPrice: Number(product.price || 0),
        });
        syncSelectedItems();
        renderProducts();
    };

    const setProductQuantity = (productId, quantity) => {
        const entry = selectedItems.get(String(productId));

        if (!entry) {
            return;
        }

        if (quantity <= 0) {
            selectedItems.delete(String(productId));
        } else {
            entry.quantity = quantity;
            selectedItems.set(String(productId), entry);
        }

        syncSelectedItems();
        renderProducts();
    };

    const renderProducts = () => {
        if (!productGrid) {
            return;
        }

        const search = String(productSearch?.value || '').trim().toLowerCase();
        const visibleProducts = products.filter(product => {
            const matchesCategory = activeCategory === 'all' || product.categoryId === activeCategory;
            const matchesSearch = !search || String(product.searchText || '').includes(search);

            return matchesCategory && matchesSearch;
        });

        if (!visibleProducts.length) {
            productGrid.innerHTML = '<div class="waiter-empty-grid meta-box">No encontramos productos con esos filtros.</div>';
            return;
        }

        const productCard = product => {
            const selectedQuantity = selectedItems.get(String(product.id))?.quantity || 0;
            const mediaMarkup = product.imageUrl
                ? '<img src="' + escapeHtml(product.imageUrl) + '" alt="' + escapeHtml(product.name) + '" loading="lazy" decoding="async">'
                : placeholderMarkup('fas fa-utensils');

            return (
                '<button type="button" class="waiter-menu-card" data-manual-product="' + product.id + '">' +
                    '<div class="waiter-menu-card-media">' + mediaMarkup + '</div>' +
                    '<div class="waiter-menu-card-copy">' +
                        '<div class="waiter-product-heading">' +
                            '<div>' +
                                '<h6>' + escapeHtml(product.name) + '</h6>' +
                                '<div class="table-note">' + escapeHtml(product.categoryName) + '</div>' +
                            '</div>' +
                            '<span class="waiter-type-pill">' + escapeHtml(product.type || 'simple') + '</span>' +
                        '</div>' +
                        '<p>' + escapeHtml(product.description || 'Sin descripcion adicional.') + '</p>' +
                        '<div class="waiter-product-footer">' +
                            '<strong>' + money(product.price) + '</strong>' +
                            '<span>' + (selectedQuantity > 0 ? 'x' + selectedQuantity + ' en cobro' : 'Tocar para agregar') + '</span>' +
                        '</div>' +
                    '</div>' +
                '</button>'
            );
        };

        if (search) {
            productGrid.innerHTML = '<div class="waiter-menu-grid">' + visibleProducts.map(productCard).join('') + '</div>';
            return;
        }

        productGrid.innerHTML = categories.map(category => {
            const categoryProducts = visibleProducts.filter(product => product.categoryId === category.id);

            if (!categoryProducts.length) {
                return '';
            }

            return '' +
                '<section class="waiter-menu-category-group">' +
                    '<div class="waiter-menu-category-heading">' +
                        '<div>' +
                            '<div class="summary-kicker">' + escapeHtml(category.name) + '</div>' +
                            '<p class="table-note mb-0">' + escapeHtml(category.description || 'Productos listos para agregar al cobro.') + '</p>' +
                        '</div>' +
                        '<span class="summary-chip">' + categoryProducts.length + (categoryProducts.length === 1 ? ' producto' : ' productos') + '</span>' +
                    '</div>' +
                    '<div class="waiter-menu-grid">' + categoryProducts.map(productCard).join('') + '</div>' +
                '</section>';
        }).join('');
    };

    oldItems.forEach(item => {
        const product = findProduct(item.product_id);

        if (product) {
            selectedItems.set(String(product.id), {
                product,
                quantity: Math.max(1, Number(item.quantity || 1)),
                unitPrice: Number(item.unit_price || product.price || 0),
            });
        }
    });

    productGrid?.addEventListener('click', function (event) {
        const card = event.target.closest('[data-manual-product]');

        if (!card) {
            return;
        }

        addProduct(findProduct(card.dataset.manualProduct));
    });

    selectedItemsContainer?.addEventListener('click', function (event) {
        const decrease = event.target.closest('[data-decrease-product]');
        const increase = event.target.closest('[data-increase-product]');
        const remove = event.target.closest('[data-remove-product]');

        if (decrease) {
            const entry = selectedItems.get(String(decrease.dataset.decreaseProduct));
            setProductQuantity(decrease.dataset.decreaseProduct, (entry?.quantity || 1) - 1);
        }

        if (increase) {
            const entry = selectedItems.get(String(increase.dataset.increaseProduct));
            setProductQuantity(increase.dataset.increaseProduct, (entry?.quantity || 0) + 1);
        }

        if (remove) {
            selectedItems.delete(String(remove.dataset.removeProduct));
            syncSelectedItems();
            renderProducts();
        }
    });

    productSearch?.addEventListener('input', renderProducts);
    paymentMethod.addEventListener('change', function () {
        if (!isCashPayment()) {
            amountReceived.dataset.userEdited = 'false';
        }

        syncTotals();
    });
    amountReceived.addEventListener('input', function () {
        amountReceived.dataset.userEdited = 'true';
        syncTotals();
    });
    creditMode.addEventListener('change', function () {
        amountReceived.dataset.userEdited = 'false';
        syncCustomerFieldState();
        syncDocumentHelp();
        syncTotals();
    });
    documentType.addEventListener('change', function () {
        syncCustomerFieldState();
        syncDocumentHelp();
        syncTotals();
    });
    customerSelect.addEventListener('change', function () {
        amountReceived.dataset.userEdited = 'false';
        syncCustomerFieldState();
        syncDocumentHelp();
        syncTotals();
    });
    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        if (selectedItems.size === 0) {
            const message = 'Selecciona al menos un producto del menu antes de registrar el cobro.';
            window.Swal ? await Swal.fire({ icon: 'warning', title: 'Faltan productos', text: message, confirmButtonText: 'Aceptar', confirmButtonColor: '#2563eb' }) : alert(message);
            return;
        }

        if (shouldApplyCustomerBalance() && !selectedCustomer()) {
            const message = 'Para usar saldo a favor o dejar credito debes seleccionar un cliente creado.';
            window.Swal ? await Swal.fire({ icon: 'warning', title: 'Falta el cliente', text: message, confirmButtonText: 'Aceptar', confirmButtonColor: '#2563eb' }) : alert(message);
            return;
        }

        if (documentType.value === 'electronic' && !selectedCustomer()) {
            const message = 'Selecciona un cliente creado para emitir factura electronica.';
            window.Swal ? await Swal.fire({ icon: 'warning', title: 'Falta el cliente', text: message, confirmButtonText: 'Aceptar', confirmButtonColor: '#2563eb' }) : alert(message);
            return;
        }

        const submitButton = form.querySelector('button[type="submit"]');
        const originalLabel = submitButton.innerHTML;
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registrando...';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: new FormData(form),
            });
            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                const message = data?.errors ? Object.values(data.errors).flat().join('\n') : (data?.message || 'No se pudo registrar el cobro.');
                window.Swal ? await Swal.fire({ icon: 'error', title: 'No se pudo registrar', text: message, confirmButtonText: 'Aceptar', confirmButtonColor: '#dc3545' }) : alert(message);
                return;
            }

            if (window.Swal) {
                await Swal.fire({
                    icon: data?.cufe ? 'success' : 'info',
                    title: data?.cufe ? 'Cobro y factura listos' : 'Cobro registrado',
                    text: data?.message || 'El documento se esta preparando para impresion.',
                    confirmButtonText: 'Continuar',
                    confirmButtonColor: '#2563eb',
                });
            }

            window.location.href = data?.printUrl || form.action;
        } catch (error) {
            window.Swal ? await Swal.fire({ icon: 'error', title: 'Error inesperado', text: 'No se pudo registrar el cobro.', confirmButtonText: 'Aceptar', confirmButtonColor: '#dc3545' }) : alert('No se pudo registrar el cobro.');
        } finally {
            submitButton.disabled = false;
            submitButton.innerHTML = originalLabel;
        }
    });

    syncCustomerFieldState();
    syncDocumentHelp();
    syncCustomerSummary();
    renderProducts();
    syncSelectedItems();
});
</script>
@endif
@endsection
