@extends('layouts.app')

@section('title', $pageTitle . ' - RestaurantePOS')

@section('content')
@php
    $productCatalog = $availableProducts->values()->map(fn ($product, $index) => [
        'id' => (int) $product->id,
        'name' => $product->name,
        'description' => $product->description,
        'price' => (float) $product->price,
        'type' => $product->product_type ?: 'simple',
        'catalogIndex' => (int) $index,
        'sortOrder' => (int) ($product->sort_order ?? 0),
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

    $menuCategories = $availableProducts
        ->groupBy(fn ($product) => $product->category_id ? 'category-' . $product->category_id : 'uncategorized')
        ->map(function ($products, $categoryKey) {
            $firstProduct = $products->first();

            return [
                'id' => $categoryKey,
                'name' => $firstProduct->menuCategory->name ?? 'Sin categoria',
                'description' => $firstProduct->menuCategory->description ?? 'Productos listos para agregar al domicilio.',
                'count' => $products->count(),
            ];
        })
        ->values();

    $draftItems = collect($deliveryRows)
        ->map(function (array $row) use ($availableProducts) {
            $productId = (int) ($row['product_id'] ?? 0);
            $quantity = max(1, (int) ($row['quantity'] ?? 1));
            $product = $availableProducts->firstWhere('id', $productId);

            if (! $product) {
                return null;
            }

            return [
                'productId' => $productId,
                'quantity' => $quantity,
            ];
        })
        ->filter()
        ->values();

    $initialDeliveryFee = (float) old('delivery_fee', $delivery->delivery_fee ?? 0);
    $initialDeliveryFeeIsFree = (string) old('delivery_fee_is_free', (float) ($delivery->delivery_fee ?? 0) <= 0 ? '1' : '0') === '1';
    $initialCustomerPayment = (float) old('customer_payment_amount', $delivery->customer_payment_amount ?? 0);
@endphp

    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Pedidos / Domicilios</span>
                <h1>{{ $pageTitle }}</h1>
                <p>Arma el pedido desde el menu, calcula el cobro del domicilio en tiempo real y deja listo cuanto cambio debe llevar el domiciliario.</p>
            </div>
        </section>

        @include('products.partials.form-errors')

        <div class="card module-card service-card">
            <div class="card-body">
                <form method="POST" action="{{ $formAction }}" id="deliveryForm">
                    @csrf
                    @if($delivery->exists)
                        @method('PUT')
                    @endif

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="customer_id">Cliente vinculado</label>
                            <select class="form-select" id="customer_id" name="customer_id">
                                <option value="">Sin vincular</option>
                                @foreach($customers as $customer)
                                    <option
                                        value="{{ $customer->id }}"
                                        data-customer-name="{{ $customer->name }}"
                                        data-customer-phone="{{ $customer->phone }}"
                                        @selected((string) old('customer_id', $delivery->customer_id) === (string) $customer->id)
                                    >
                                        {{ $customer->name }}{{ $customer->phone ? ' - ' . $customer->phone : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-help mt-1" id="customerSelectionHelp">Puedes dejarlo sin vincular o cargar los datos del cliente registrado.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="delivery_driver_id">Responsable</label>
                            <select class="form-select" id="delivery_driver_id" name="delivery_driver_id">
                                <option value="">Sin asignar</option>
                                @foreach($deliveryDrivers as $deliveryDriver)
                                    <option value="{{ $deliveryDriver->id }}" @selected((string) old('delivery_driver_id', $delivery->delivery_driver_id) === (string) $deliveryDriver->id)>{{ $deliveryDriver->name }}{{ $deliveryDriver->is_active ? '' : ' (inactivo)' }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Listado de domiciliarios.</div>
                            @if(Auth::user()->hasRole('Admin') || Auth::user()->hasPermission('delivery_drivers.create'))
                                <a href="{{ route('deliveries.drivers.create') }}" class="btn btn-link px-0">Registrar nuevo domiciliario</a>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="customer_name">Nombre del cliente</label>
                            <input type="text" class="form-control" id="customer_name" name="customer_name" value="{{ old('customer_name', $delivery->customer_name) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="customer_phone">Telefono</label>
                            <input type="text" class="form-control" id="customer_phone" name="customer_phone" value="{{ old('customer_phone', $delivery->customer_phone) }}">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="delivery_address">Direccion de entrega</label>
                            <input type="text" class="form-control" id="delivery_address" name="delivery_address" value="{{ old('delivery_address', $delivery->delivery_address) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="reference">Referencia</label>
                            <input type="text" class="form-control" id="reference" name="reference" value="{{ old('reference', $delivery->reference) }}" placeholder="Ej: casa azul, porteria, segundo piso">
                        </div>
                    </div>

                    <div class="row g-3 mt-2">
                        <div class="col-md-4">
                            <label class="form-label" for="scheduled_at">Programado para</label>
                            <input type="datetime-local" class="form-control" id="scheduled_at" name="scheduled_at" value="{{ old('scheduled_at', optional($delivery->scheduled_at)->format('Y-m-d\TH:i')) }}">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="notes">Notas</label>
                            <textarea class="form-control" id="notes" name="notes" rows="4">{{ old('notes', $delivery->notes) }}</textarea>
                        </div>
                        @if($delivery->delivery_proof_image_url)
                            <div class="col-12">
                                <label class="form-label d-block">Evidencia de entrega</label>
                                <a href="{{ $delivery->delivery_proof_image_url }}" target="_blank" rel="noopener noreferrer">
                                    <img src="{{ $delivery->delivery_proof_image_url }}" alt="Evidencia del domicilio" loading="lazy" decoding="async" style="width: 180px; height: 180px; object-fit: cover; border-radius: 18px; border: 1px solid #dbe3f1;">
                                </a>
                            </div>
                        @endif
                    </div>

                    <div class="mt-4">
                        @if($availableProducts->isEmpty())
                            <div class="empty-state py-4">
                                <i class="fas fa-utensils"></i>
                                <h5 class="mb-2">No hay productos disponibles para domicilios</h5>
                                <p class="mb-0">Activa categorias y productos en el modulo de menu para poder tomar pedidos a domicilio.</p>
                            </div>
                        @else
                            <div class="waiter-entry-layout">
                                <div class="waiter-catalog-shell">
                                    <div class="waiter-catalog-topbar">
                                        <div>
                                            <div class="summary-kicker">Carta del restaurante</div>
                                            <h6 class="mb-1">Selecciona los productos del domicilio</h6>
                                            <p class="table-note mb-0">Toca un producto para sumarlo. El total del pedido se recalcula automaticamente.</p>
                                        </div>
                                        <div class="waiter-catalog-search">
                                            <label class="form-label" for="menuProductSearch">Buscar producto</label>
                                            <input type="search" class="form-control" id="menuProductSearch" placeholder="Ej: hamburguesa, jugo">
                                        </div>
                                    </div>

                                    <div class="menu-category-tabs" id="menuCategoryTabs">
                                        <button type="button" class="menu-category-tab is-active" data-category-tab="all">
                                            Todo el menu
                                        </button>
                                        @foreach($menuCategories as $category)
                                            <button type="button" class="menu-category-tab" data-category-tab="{{ $category['id'] }}">
                                                {{ $category['name'] }}
                                                <span>{{ $category['count'] }}</span>
                                            </button>
                                        @endforeach
                                    </div>

                                    <div class="menu-category-caption" id="menuCategoryCaption">
                                        Explora toda la carta y arma el domicilio en segundos.
                                    </div>

                                    <div class="waiter-product-grid" id="waiterProductGrid"></div>
                                </div>

                                <aside class="waiter-draft-shell">
                                    <div class="waiter-draft-header">
                                        <div>
                                            <div class="summary-kicker">Resumen en tiempo real</div>
                                            <h6 class="mb-1">Pedido del domicilio</h6>
                                            <p class="table-note mb-0">El total final suma el pedido mas el costo del domicilio si no es gratis.</p>
                                        </div>
                                        <span class="summary-chip" id="draftUniqueItemsChip">0 referencias</span>
                                    </div>

                                    <div class="waiter-draft-stats">
                                        <div class="meta-box">
                                            <div class="summary-kicker">Unidades</div>
                                            <div class="fw-bold" id="draftUnitsCount">0</div>
                                        </div>
                                        <div class="meta-box">
                                            <div class="summary-kicker">Subtotal pedido</div>
                                            <div class="fw-bold" id="draftSubtotal">$0</div>
                                        </div>
                                    </div>

                                    <div class="waiter-empty-draft meta-box" id="waiterEmptyDraft">
                                        Toca los productos del menu para construir el domicilio.
                                    </div>

                                    <div class="waiter-draft-items" id="waiterDraftItems"></div>
                                    <div id="deliveryItemsInputs"></div>

                                    <div class="meta-box mt-3">
                                        <div class="summary-kicker mb-2">Cobro y entrega</div>

                                        <input type="hidden" name="delivery_fee_is_free" value="0">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" role="switch" id="delivery_fee_is_free" name="delivery_fee_is_free" value="1" @checked($initialDeliveryFeeIsFree)>
                                            <label class="form-check-label" for="delivery_fee_is_free">Domicilio gratis</label>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label" for="delivery_fee">Costo domicilio</label>
                                            <input
                                                type="number"
                                                step="1"
                                                min="0"
                                                class="form-control"
                                                id="delivery_fee"
                                                name="delivery_fee"
                                                value="{{ money_input($initialDeliveryFee) }}"
                                                data-last-custom-fee="{{ money_input(max($initialDeliveryFee, 0)) }}"
                                            >
                                            <div class="form-help mt-1" id="deliveryFeeModeHelp">Marca la opcion gratis si no se debe cobrar envio.</div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label" for="customer_payment_amount">Paga con</label>
                                            <input
                                                type="number"
                                                step="1"
                                                min="0"
                                                class="form-control"
                                                id="customer_payment_amount"
                                                name="customer_payment_amount"
                                                value="{{ money_input($initialCustomerPayment) }}"
                                                data-user-edited="{{ $initialCustomerPayment > 0 ? 'true' : 'false' }}"
                                                required
                                            >
                                            <div class="form-help mt-1" id="paymentChangeHelp">Ingresa con cuanto paga el cliente para calcular el cambio del domiciliario.</div>
                                        </div>

                                        <input type="hidden" id="order_total" name="order_total" value="{{ money_input(old('order_total', $delivery->order_total ?? 0)) }}">

                                        <div class="d-flex justify-content-between gap-3">
                                            <span class="summary-kicker">Subtotal pedido</span>
                                            <strong id="orderSubtotalPreview">$0</strong>
                                        </div>
                                        <div class="d-flex justify-content-between gap-3 mt-2">
                                            <span class="summary-kicker">Costo domicilio</span>
                                            <strong id="deliveryFeePreview">$0</strong>
                                        </div>
                                        <div class="d-flex justify-content-between gap-3 mt-2">
                                            <span class="summary-kicker">Total domicilio</span>
                                            <strong id="totalChargePreview">$0</strong>
                                        </div>
                                        <div class="d-flex justify-content-between gap-3 mt-2">
                                            <span class="summary-kicker">Cambio a devolver</span>
                                            <strong id="changeRequiredPreview">$0</strong>
                                        </div>
                                    </div>
                                </aside>
                            </div>
                        @endif
                    </div>

                    <div class="form-actions mt-4">
                        <a href="{{ route('deliveries.index') }}" class="btn btn-outline-secondary">Volver</a>
                        <button type="submit" class="btn btn-primary" id="submitDeliveryButton" @if($availableProducts->isEmpty()) disabled @endif>{{ $submitLabel }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const deliveryForm = document.getElementById('deliveryForm');
            const customerSelect = document.getElementById('customer_id');
            const customerNameInput = document.getElementById('customer_name');
            const customerPhoneInput = document.getElementById('customer_phone');
            const customerSelectionHelp = document.getElementById('customerSelectionHelp');
            const submitDeliveryButton = document.getElementById('submitDeliveryButton');
            const products = @json($productCatalog);
            const initialDraftItems = @json($draftItems);
            const categories = @json($menuCategories);
            const productSearchInput = document.getElementById('menuProductSearch');
            const productGrid = document.getElementById('waiterProductGrid');
            const draftItemsContainer = document.getElementById('waiterDraftItems');
            const draftInputsContainer = document.getElementById('deliveryItemsInputs');
            const emptyDraftState = document.getElementById('waiterEmptyDraft');
            const draftUnitsCount = document.getElementById('draftUnitsCount');
            const draftUniqueItemsChip = document.getElementById('draftUniqueItemsChip');
            const draftSubtotal = document.getElementById('draftSubtotal');
            const categoryTabs = Array.from(document.querySelectorAll('[data-category-tab]'));
            const categoryCaption = document.getElementById('menuCategoryCaption');
            const orderTotalInput = document.getElementById('order_total');
            const deliveryFeeFreeInput = document.getElementById('delivery_fee_is_free');
            const deliveryFeeInput = document.getElementById('delivery_fee');
            const customerPaymentInput = document.getElementById('customer_payment_amount');
            const orderSubtotalPreview = document.getElementById('orderSubtotalPreview');
            const deliveryFeePreview = document.getElementById('deliveryFeePreview');
            const totalChargePreview = document.getElementById('totalChargePreview');
            const changeRequiredPreview = document.getElementById('changeRequiredPreview');
            const paymentChangeHelp = document.getElementById('paymentChangeHelp');
            const deliveryFeeModeHelp = document.getElementById('deliveryFeeModeHelp');

            const money = value => '$' + Math.round(Number(value || 0)).toLocaleString('es-CO');
            const moneyInput = value => String(Math.max(0, Math.round(Number(value || 0))));

            const syncCustomerSelection = (fillOnlyWhenEmpty = false) => {
                if (!customerSelect || !customerSelectionHelp) {
                    return;
                }

                const selectedOption = customerSelect.options[customerSelect.selectedIndex];

                if (!selectedOption || selectedOption.value === '') {
                    customerSelectionHelp.textContent = 'Puedes dejarlo sin vincular o cargar los datos del cliente registrado.';
                    return;
                }

                customerSelectionHelp.textContent = 'Cliente vinculado: ' + selectedOption.textContent + '.';

                if (customerNameInput && (!fillOnlyWhenEmpty || customerNameInput.value.trim() === '')) {
                    customerNameInput.value = selectedOption.dataset.customerName || customerNameInput.value;
                }

                if (customerPhoneInput && (!fillOnlyWhenEmpty || customerPhoneInput.value.trim() === '')) {
                    customerPhoneInput.value = selectedOption.dataset.customerPhone || customerPhoneInput.value;
                }
            };

            if (customerSelect) {
                customerSelect.addEventListener('change', function () {
                    syncCustomerSelection(false);
                });

                syncCustomerSelection(true);
            }

            if (!deliveryForm || !productGrid || !draftItemsContainer || !draftInputsContainer || !emptyDraftState || !draftUnitsCount || !draftUniqueItemsChip || !draftSubtotal) {
                return;
            }

            const productMap = new Map(products.map(product => [String(product.id), product]));
            const selectedItems = new Map();
            let activeCategory = 'all';
            let lastCustomDeliveryFee = Math.max(0, Number(deliveryFeeInput?.dataset.lastCustomFee || deliveryFeeInput?.value || 0));

            const escapeHtml = value => String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
            const typeLabel = () => 'Producto';
            const placeholderMarkup = icon => '<div class="waiter-image-placeholder"><i class="' + icon + '"></i></div>';
            const findCategory = categoryId => categories.find(category => category.id === categoryId);

            const seedDraftItems = () => {
                initialDraftItems.forEach(item => {
                    const product = productMap.get(String(item.productId));

                    if (!product) {
                        return;
                    }

                    const currentQuantity = selectedItems.get(String(product.id))?.quantity || 0;

                    selectedItems.set(String(product.id), {
                        product,
                        quantity: currentQuantity + Math.max(1, Number(item.quantity || 1)),
                    });
                });
            };

            const filteredProducts = () => {
                const searchTerm = (productSearchInput?.value || '').toString().trim().toLowerCase();

                return products.filter(product => {
                    const matchesCategory = activeCategory === 'all' || product.categoryId === activeCategory;
                    const matchesSearch = searchTerm === '' || product.searchText.includes(searchTerm);

                    return matchesCategory && matchesSearch;
                });
            };

            const setActiveCategory = categoryId => {
                activeCategory = categoryId;

                categoryTabs.forEach(tab => {
                    tab.classList.toggle('is-active', tab.dataset.categoryTab === categoryId);
                });

                const category = categoryId === 'all' ? null : findCategory(categoryId);
                if (categoryCaption) {
                    categoryCaption.textContent = category
                        ? category.description
                        : 'Explora toda la carta y arma el domicilio en segundos.';
                }

                renderProductGrid();
            };

            const syncFinancialSummary = () => {
                const entries = Array.from(selectedItems.values());
                const orderSubtotal = entries.reduce((total, entry) => total + (entry.product.price * entry.quantity), 0);
                const deliveryIsFree = deliveryFeeFreeInput ? deliveryFeeFreeInput.checked : false;

                if (deliveryFeeInput) {
                    const currentFee = Math.max(0, Number(deliveryFeeInput.value || 0));

                    if (deliveryIsFree) {
                        if (!deliveryFeeInput.disabled && currentFee > 0) {
                            lastCustomDeliveryFee = currentFee;
                        }

                        deliveryFeeInput.value = '0';
                        deliveryFeeInput.disabled = true;
                        deliveryFeeInput.classList.add('bg-light');

                        if (deliveryFeeModeHelp) {
                            deliveryFeeModeHelp.textContent = 'No se sumara costo de domicilio al total.';
                        }
                    } else {
                        deliveryFeeInput.disabled = false;
                        deliveryFeeInput.classList.remove('bg-light');

                        if (currentFee <= 0 && lastCustomDeliveryFee > 0) {
                            deliveryFeeInput.value = moneyInput(lastCustomDeliveryFee);
                        }

                        if (deliveryFeeModeHelp) {
                            deliveryFeeModeHelp.textContent = 'Este valor se sumara al total del pedido.';
                        }
                    }
                }

                const deliveryFee = deliveryIsFree
                    ? 0
                    : Math.max(0, Number(deliveryFeeInput?.value || 0));
                const totalCharge = orderSubtotal + deliveryFee;

                if (orderTotalInput) {
                    orderTotalInput.value = moneyInput(orderSubtotal);
                }

                if (customerPaymentInput) {
                    const currentPayment = Math.max(0, Number(customerPaymentInput.value || 0));

                    if (customerPaymentInput.dataset.userEdited !== 'true' || currentPayment < totalCharge) {
                        customerPaymentInput.value = moneyInput(totalCharge);
                    }
                }

                const customerPayment = Math.max(0, Number(customerPaymentInput?.value || 0));
                const changeRequired = Math.max(customerPayment - totalCharge, 0);
                const pendingAmount = Math.max(totalCharge - customerPayment, 0);

                if (orderSubtotalPreview) {
                    orderSubtotalPreview.textContent = money(orderSubtotal);
                }

                if (draftSubtotal) {
                    draftSubtotal.textContent = money(orderSubtotal);
                }

                if (deliveryFeePreview) {
                    deliveryFeePreview.textContent = money(deliveryFee);
                }

                if (totalChargePreview) {
                    totalChargePreview.textContent = money(totalCharge);
                }

                if (changeRequiredPreview) {
                    changeRequiredPreview.textContent = money(changeRequired);
                }

                if (paymentChangeHelp) {
                    paymentChangeHelp.textContent = pendingAmount > 0
                        ? 'Faltan ' + money(pendingAmount) + ' para cubrir el domicilio.'
                        : (changeRequired > 0
                            ? 'El domiciliario debe llevar ' + money(changeRequired) + ' de cambio.'
                            : 'El cliente paga exacto. No necesita llevar cambio.');
                }
            };

            const renderProductGrid = () => {
                const visibleProducts = filteredProducts();

                if (visibleProducts.length === 0) {
                    productGrid.innerHTML = '<div class="meta-box waiter-empty-grid">No encontramos productos para ese filtro. Cambia la categoria o limpia la busqueda.</div>';
                    return;
                }

                productGrid.innerHTML = visibleProducts.map(product => {
                    const selectedQuantity = selectedItems.get(String(product.id))?.quantity || 0;
                    const mediaMarkup = product.imageUrl
                        ? '<img src="' + escapeHtml(product.imageUrl) + '" alt="' + escapeHtml(product.name) + '" loading="lazy" decoding="async">'
                        : placeholderMarkup('fas fa-utensils');

                    return '' +
                        '<button type="button" class="waiter-product-card" data-add-product="' + product.id + '">' +
                            '<div class="waiter-product-media">' + mediaMarkup + '</div>' +
                            '<div class="waiter-product-copy">' +
                                '<div class="waiter-product-heading">' +
                                    '<div>' +
                                        '<h6>' + escapeHtml(product.name) + '</h6>' +
                                        '<div class="table-note">' + escapeHtml(product.categoryName) + '</div>' +
                                    '</div>' +
                                    '<span class="waiter-type-pill">' + escapeHtml(typeLabel(product.type)) + '</span>' +
                                '</div>' +
                                '<p>' + escapeHtml(product.description || 'Sin descripcion adicional.') + '</p>' +
                                '<div class="waiter-product-footer">' +
                                    '<strong>' + money(product.price) + '</strong>' +
                                    '<span>' + (selectedQuantity > 0 ? 'x' + selectedQuantity + ' en domicilio' : 'Tocar para agregar') + '</span>' +
                                '</div>' +
                            '</div>' +
                        '</button>';
                }).join('');
            };

            const renderDraft = () => {
                const entries = Array.from(selectedItems.values()).sort((left, right) => left.product.catalogIndex - right.product.catalogIndex);
                const units = entries.reduce((total, entry) => total + entry.quantity, 0);

                draftUnitsCount.textContent = String(units);
                draftUniqueItemsChip.textContent = entries.length + (entries.length === 1 ? ' referencia' : ' referencias');
                emptyDraftState.style.display = entries.length === 0 ? '' : 'none';

                draftItemsContainer.innerHTML = entries.map(entry => {
                    const mediaMarkup = entry.product.imageUrl
                        ? '<img src="' + escapeHtml(entry.product.imageUrl) + '" alt="' + escapeHtml(entry.product.name) + '" loading="lazy" decoding="async">'
                        : placeholderMarkup('fas fa-image');

                    return '' +
                        '<article class="waiter-draft-item">' +
                            '<div class="waiter-draft-thumb">' + mediaMarkup + '</div>' +
                            '<div class="waiter-draft-copy">' +
                                '<div class="d-flex justify-content-between gap-3 align-items-start">' +
                                    '<div>' +
                                        '<h6 class="mb-1">' + escapeHtml(entry.product.name) + '</h6>' +
                                        '<div class="table-note">' + escapeHtml(typeLabel(entry.product.type)) + ' | ' + money(entry.product.price) + '</div>' +
                                    '</div>' +
                                    '<button type="button" class="btn btn-link text-danger p-0 waiter-remove-line" data-qty-product="' + entry.product.id + '" data-qty-change="' + (-entry.quantity) + '">Quitar</button>' +
                                '</div>' +
                                '<div class="waiter-draft-controls">' +
                                    '<div class="waiter-qty-controls">' +
                                        '<button type="button" class="btn btn-outline-secondary btn-sm" data-qty-product="' + entry.product.id + '" data-qty-change="-1">-</button>' +
                                        '<span>' + entry.quantity + '</span>' +
                                        '<button type="button" class="btn btn-outline-secondary btn-sm" data-qty-product="' + entry.product.id + '" data-qty-change="1">+</button>' +
                                    '</div>' +
                                    '<strong>' + money(entry.product.price * entry.quantity) + '</strong>' +
                                '</div>' +
                            '</div>' +
                        '</article>';
                }).join('');

                draftInputsContainer.innerHTML = entries.map((entry, index) => {
                    return '' +
                        '<input type="hidden" name="items[' + index + '][product_id]" value="' + entry.product.id + '">' +
                        '<input type="hidden" name="items[' + index + '][quantity]" value="' + entry.quantity + '">';
                }).join('');

                if (submitDeliveryButton) {
                    submitDeliveryButton.disabled = entries.length === 0;
                }

                syncFinancialSummary();
            };

            const changeItemQuantity = (productId, delta) => {
                const key = String(productId);
                const entry = selectedItems.get(key);

                if (!entry) {
                    if (delta > 0) {
                        const product = productMap.get(key);

                        if (product) {
                            selectedItems.set(key, { product, quantity: delta });
                        }
                    }

                    renderDraft();
                    renderProductGrid();
                    return;
                }

                const nextQuantity = entry.quantity + delta;

                if (nextQuantity <= 0) {
                    selectedItems.delete(key);
                } else {
                    selectedItems.set(key, { product: entry.product, quantity: nextQuantity });
                }

                renderDraft();
                renderProductGrid();
            };

            if (deliveryFeeFreeInput) {
                deliveryFeeFreeInput.addEventListener('change', syncFinancialSummary);
            }

            if (deliveryFeeInput) {
                deliveryFeeInput.addEventListener('input', function () {
                    const fee = Math.max(0, Number(deliveryFeeInput.value || 0));

                    if (fee > 0) {
                        lastCustomDeliveryFee = fee;
                    }

                    syncFinancialSummary();
                });
            }

            if (customerPaymentInput) {
                customerPaymentInput.addEventListener('input', function () {
                    customerPaymentInput.dataset.userEdited = 'true';
                    syncFinancialSummary();
                });
            }

            if (productSearchInput) {
                productSearchInput.addEventListener('input', renderProductGrid);
            }

            categoryTabs.forEach(tab => {
                tab.addEventListener('click', function () {
                    setActiveCategory(tab.dataset.categoryTab);
                });
            });

            productGrid.addEventListener('click', function (event) {
                const card = event.target.closest('[data-add-product]');

                if (!card) {
                    return;
                }

                changeItemQuantity(card.dataset.addProduct, 1);
            });

            draftItemsContainer.addEventListener('click', function (event) {
                const control = event.target.closest('[data-qty-product]');

                if (!control) {
                    return;
                }

                changeItemQuantity(control.dataset.qtyProduct, Number(control.dataset.qtyChange || 0));
            });

            deliveryForm.addEventListener('submit', async function (event) {
                if (selectedItems.size > 0) {
                    return;
                }

                event.preventDefault();

                if (window.Swal) {
                    await Swal.fire({
                        icon: 'warning',
                        title: 'Falta el pedido',
                        text: 'Selecciona al menos un producto antes de guardar el domicilio.',
                        confirmButtonText: 'Aceptar',
                        confirmButtonColor: '#2563eb',
                    });
                } else {
                    alert('Selecciona al menos un producto antes de guardar el domicilio.');
                }
            });

            seedDraftItems();
            renderDraft();
            setActiveCategory('all');
            syncFinancialSummary();
        });
    </script>
@endsection
