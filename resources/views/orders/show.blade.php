@extends('layouts.app')

@section('title', 'Pedido ' . $restaurantTable->name . ' - RestaurantePOS')

@section('content')
@php
    $user = Auth::user();
    $canManageOrders = $user->hasRole('Admin') || $user->hasAnyPermission(['orders.create', 'orders.edit']);
    $statusLabels = ['free' => 'Libre', 'occupied' => 'Ocupada', 'reserved' => 'Reservada'];
    $statusClasses = ['free' => 'status-free', 'occupied' => 'status-occupied', 'reserved' => 'status-reserved'];
    $statusLabel = $statusLabels[$restaurantTable->status] ?? ucfirst($restaurantTable->status);
    $statusClass = $statusClasses[$restaurantTable->status] ?? 'status-free';

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
                'description' => $firstProduct->menuCategory->description ?? 'Productos listos para agregar al pedido.',
                'count' => $products->count(),
            ];
        })
        ->values();

    $draftItems = collect($orderRows)
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
@endphp

<div class="module-page">
    <section class="module-hero">
        <div>
            <span class="module-kicker">Pedidos por Mesa / RF-08 al RF-10</span>
            <h1>Servicio de {{ $restaurantTable->name }}</h1>
            <p>Toma el pedido con una carta tactil por categorias, agrega multiples unidades y manda la comanda a cocina sin salir de la mesa.</p>
        </div>
        <div class="summary-group">
            <span class="summary-chip">Codigo {{ $restaurantTable->code }}</span>
            <span class="summary-chip">{{ $restaurantTable->area ?: 'Salon principal' }}</span>
            <span class="summary-chip">{{ $statusLabel }}</span>
        </div>
    </section>

    @include('products.partials.form-errors')

    <div>
        <div>
            <div class="card module-card service-card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h5 class="mb-1">Pedido actual</h5>
                        <p class="table-note mb-0">
                            @if($openOrder)
                                {{ $openOrder->order_number }} abierto por {{ $openOrder->openedBy->name ?? 'el equipo' }}.
                            @else
                                Esta mesa aun no tiene un pedido abierto.
                            @endif
                        </p>
                    </div>
                    <div class="table-card-actions">
                        @if($openOrder)
                            <a href="{{ route('orders.kitchen-ticket', $openOrder) }}" target="_blank" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-print"></i> Imprimir cocina
                            </a>
                        @endif
                        <span class="status-pill {{ $statusClass }}">{{ $statusLabel }}</span>
                    </div>
                </div>
                <div class="card-body">
                    @if($openOrder)
                        <div class="row g-3">
                            <div class="col-md-4"><div class="order-summary-card h-100"><div class="summary-kicker">Subtotal</div><div class="h3 mb-0">${{ number_format((float) $openOrder->subtotal, 2) }}</div></div></div>
                            <div class="col-md-4"><div class="order-summary-card h-100"><div class="summary-kicker">Impuesto</div><div class="h3 mb-0">${{ number_format((float) $openOrder->tax_amount, 2) }}</div></div></div>
                            <div class="col-md-4"><div class="order-summary-card h-100"><div class="summary-kicker">Total</div><div class="h3 mb-0">${{ number_format((float) $openOrder->total, 2) }}</div></div></div>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-lg-6">
                                <div class="meta-box h-100">
                                    <div class="summary-kicker">Cliente</div>
                                    <div class="fw-bold">{{ $openOrder->customer?->name ?: $openOrder->customer_name ?: 'Sin cliente' }}</div>
                                    <div class="seat-note">{{ $openOrder->notes ?: 'Sin notas en el pedido.' }}</div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="meta-box h-100">
                                    <div class="summary-kicker">Resumen</div>
                                    <div class="fw-bold">{{ $openOrder->items->sum('quantity') }} items</div>
                                    <div class="seat-note">{{ $splitSummary->count() ?: 1 }} cuenta{{ ($splitSummary->count() ?: 1) > 1 ? 's' : '' }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive mt-4">
                            <table class="table table-hover order-items-table align-middle mb-0">
                                <thead>
                                    <tr><th>Producto</th><th>Cantidad</th><th>Precio</th><th>Cuenta</th><th class="text-end">Subtotal</th></tr>
                                </thead>
                                <tbody>
                                    @foreach($openOrder->items as $item)
                                        <tr>
                                            <td><strong>{{ $item->product_name }}</strong><div class="table-note">{{ $item->product?->product_type === 'combo' ? 'Combo' : 'Producto del menu' }}</div></td>
                                            <td>{{ $item->quantity }}</td>
                                            <td>${{ number_format((float) $item->unit_price, 2) }}</td>
                                            <td>Cuenta {{ $item->split_group ?: 1 }}</td>
                                            <td class="text-end">${{ number_format((float) $item->subtotal, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="empty-state py-4">
                            <i class="fas fa-receipt"></i>
                            <h5 class="mb-2">No hay pedido abierto</h5>
                            <p class="mb-0">Selecciona productos desde la carta grafica para iniciar el servicio y mandar la comanda a cocina.</p>
                        </div>
                    @endif
                </div>
            </div>

            @if($canManageOrders)
                <div class="card module-card service-card" id="service-flow">
                    <div class="card-header">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                            <div>
                                <h5 class="mb-1">{{ $openOrder ? 'Agregar productos al pedido' : 'Tomar pedido para la mesa' }}</h5>
                                <p class="table-note mb-0">La carta se organiza por categorias, con seleccion rapida para pantallas tactiles y resumen en tiempo real.</p>
                            </div>
                            <span class="summary-chip">Comanda visual</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('orders.store', $restaurantTable) }}" id="orderServiceForm">
                            @csrf

                            <div class="form-grid waiter-order-meta-grid">
                                <div>
                                    <label class="form-label" for="customer_search">Cliente</label>
                                    <input type="search" class="form-control mb-2" id="customer_search" placeholder="Buscar cliente por nombre, documento, telefono o email">
                                    <select class="form-select" id="customer_id" name="customer_id">
                                        <option value="">Sin cliente</option>
                                        @foreach($availableCustomers as $customer)
                                            <option
                                                value="{{ $customer->id }}"
                                                data-search="{{ \Illuminate\Support\Str::lower(trim($customer->name . ' ' . $customer->document_number . ' ' . $customer->phone . ' ' . $customer->email)) }}"
                                                @selected((string) old('customer_id', $openOrder?->customer_id) === (string) $customer->id)
                                            >
                                                {{ $customer->name }}@if($customer->document_number) - {{ $customer->document_number }}@endif
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-help mt-2" id="customerSelectionHelp">
                                        @if(old('customer_id', $openOrder?->customer_id))
                                            Cliente seleccionado: {{ $openOrder?->customer?->name ?: 'Cliente cargado' }}.
                                        @else
                                            Se usara la opcion sin cliente a menos que selecciones uno.
                                        @endif
                                    </div>
                                </div>

                                <div>
                                    <label class="form-label" for="notes">Notas del pedido</label>
                                    <input type="text" class="form-control" id="notes" name="notes" value="{{ old('notes', $openOrder?->notes) }}" placeholder="Ej: sin cebolla, termino medio, mesa de cumpleanos">
                                    <div class="form-help">Estas notas viajaran junto con la comanda enviada a cocina.</div>
                                </div>
                            </div>

                            @if($availableProducts->isEmpty())
                                <div class="empty-state py-4">
                                    <i class="fas fa-utensils"></i>
                                    <h5 class="mb-2">No hay productos disponibles para pedir</h5>
                                    <p class="mb-0">Activa categorias y productos en el modulo de menu para poder registrar pedidos desde esta mesa.</p>
                                </div>
                            @else
                                <div class="waiter-entry-layout">
                                    <div class="waiter-catalog-shell">
                                        <div class="waiter-catalog-topbar">
                                            <div>
                                                <div class="summary-kicker">Carta del restaurante</div>
                                                <h6 class="mb-1">Seleccion rapida por categorias</h6>
                                                <p class="table-note mb-0">Toca un producto para sumarlo al pedido. Puedes usar la busqueda para encontrarlo mas rapido.</p>
                                            </div>
                                            <div class="waiter-catalog-search">
                                                <label class="form-label" for="menuProductSearch">Buscar producto</label>
                                                <input type="search" class="form-control" id="menuProductSearch" placeholder="Ej: limonada, pasta, postre">
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
                                            Explora toda la carta y agrega productos al pedido en segundos.
                                        </div>

                                        <div class="waiter-product-grid" id="waiterProductGrid"></div>
                                    </div>

                                    <aside class="waiter-draft-shell">
                                        <div class="waiter-draft-header">
                                            <div>
                                                <div class="summary-kicker">Resumen en tiempo real</div>
                                                <h6 class="mb-1">Pedido en armado</h6>
                                                <p class="table-note mb-0">El impuesto se recalcula cuando guardes la comanda.</p>
                                            </div>
                                            <span class="summary-chip" id="draftUniqueItemsChip">0 referencias</span>
                                        </div>

                                        <div class="waiter-draft-stats">
                                            <div class="meta-box">
                                                <div class="summary-kicker">Unidades</div>
                                                <div class="fw-bold" id="draftUnitsCount">0</div>
                                            </div>
                                            <div class="meta-box">
                                                <div class="summary-kicker">Subtotal nuevo</div>
                                                <div class="fw-bold" id="draftSubtotal">$0.00</div>
                                            </div>
                                        </div>

                                        <div class="waiter-empty-draft meta-box" id="waiterEmptyDraft">
                                            Toca los productos del menu para construir el pedido de esta mesa.
                                        </div>

                                        <div class="waiter-draft-items" id="waiterDraftItems"></div>
                                        <div id="orderItemsInputs"></div>

                                        <div class="form-actions waiter-form-actions">
                                            <a href="{{ route('orders.index') }}" class="btn btn-outline-secondary">Volver a pedidos</a>
                                            <button type="submit" class="btn btn-primary" id="submitOrderButton">{{ $openOrder ? 'Guardar y mandar a cocina' : 'Crear pedido y mandar a cocina' }}</button>
                                        </div>
                                    </aside>
                                </div>
                            @endif
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

@if($canManageOrders)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const orderServiceForm = document.getElementById('orderServiceForm');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const customerSearch = document.getElementById('customer_search');
    const customerSelect = document.getElementById('customer_id');
    const customerSelectionHelp = document.getElementById('customerSelectionHelp');
    const customerOptions = customerSelect ? Array.from(customerSelect.options).slice(1).map(option => ({
        value: option.value,
        label: option.textContent,
        search: option.dataset.search || '',
    })) : [];

    const updateCustomerHelp = () => {
        if (!customerSelect || !customerSelectionHelp) {
            return;
        }

        const selectedOption = customerSelect.options[customerSelect.selectedIndex];

        if (!selectedOption || selectedOption.value === '') {
            customerSelectionHelp.textContent = 'Se usara la opcion sin cliente a menos que selecciones uno.';
            return;
        }

        customerSelectionHelp.textContent = 'Cliente seleccionado: ' + selectedOption.textContent + '.';
    };

    const renderCustomerOptions = searchTerm => {
        if (!customerSelect) {
            return;
        }

        const normalizedSearch = (searchTerm || '').toString().trim().toLowerCase();
        const currentValue = customerSelect.value;
        const filteredOptions = normalizedSearch === ''
            ? customerOptions
            : customerOptions.filter(option => option.search.includes(normalizedSearch));

        customerSelect.innerHTML = '<option value="">Sin cliente</option>' + filteredOptions.map(option => '<option value="' + option.value + '"' + (option.value === currentValue ? ' selected' : '') + '>' + option.label + '</option>').join('');

        if (currentValue && !filteredOptions.some(option => option.value === currentValue)) {
            customerSelect.value = '';
        }

        updateCustomerHelp();
    };

    if (customerSearch && customerSelect) {
        customerSearch.addEventListener('input', function () {
            renderCustomerOptions(customerSearch.value);
        });

        customerSelect.addEventListener('change', updateCustomerHelp);
        renderCustomerOptions('');
        updateCustomerHelp();
    }

    if (!orderServiceForm) {
        return;
    }

    const products = @json($productCatalog);
    const initialDraftItems = @json($draftItems);
    const categories = @json($menuCategories);
    const submitOrderButton = document.getElementById('submitOrderButton');
    const productSearchInput = document.getElementById('menuProductSearch');
    const productGrid = document.getElementById('waiterProductGrid');
    const draftItemsContainer = document.getElementById('waiterDraftItems');
    const draftInputsContainer = document.getElementById('orderItemsInputs');
    const emptyDraftState = document.getElementById('waiterEmptyDraft');
    const draftUnitsCount = document.getElementById('draftUnitsCount');
    const draftUniqueItemsChip = document.getElementById('draftUniqueItemsChip');
    const draftSubtotal = document.getElementById('draftSubtotal');
    const categoryTabs = Array.from(document.querySelectorAll('[data-category-tab]'));
    const categoryCaption = document.getElementById('menuCategoryCaption');

    const productMap = new Map(products.map(product => [String(product.id), product]));
    const selectedItems = new Map();
    let activeCategory = 'all';

    const escapeHtml = value => String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    const money = value => '$' + Number(value || 0).toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const typeLabel = type => type === 'combo' ? 'Combo' : 'Producto';
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

    const setActiveCategory = categoryId => {
        activeCategory = categoryId;

        categoryTabs.forEach(tab => {
            tab.classList.toggle('is-active', tab.dataset.categoryTab === categoryId);
        });

        const category = categoryId === 'all' ? null : findCategory(categoryId);
        categoryCaption.textContent = category
            ? category.description
            : 'Explora toda la carta y agrega productos al pedido en segundos.';

        renderProductGrid();
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

    const filteredProducts = () => {
        const searchTerm = (productSearchInput?.value || '').toString().trim().toLowerCase();

        return products.filter(product => {
            const matchesCategory = activeCategory === 'all' || product.categoryId === activeCategory;
            const matchesSearch = searchTerm === '' || product.searchText.includes(searchTerm);

            return matchesCategory && matchesSearch;
        });
    };

    const renderProductGrid = () => {
        if (!productGrid) {
            return;
        }

        const visibleProducts = filteredProducts();

        if (visibleProducts.length === 0) {
            productGrid.innerHTML = '<div class="meta-box waiter-empty-grid">No encontramos productos para ese filtro. Cambia la categoria o limpia la busqueda.</div>';
            return;
        }

        productGrid.innerHTML = visibleProducts.map(product => {
            const selectedQuantity = selectedItems.get(String(product.id))?.quantity || 0;
            const mediaMarkup = product.imageUrl
                ? '<img src="' + escapeHtml(product.imageUrl) + '" alt="' + escapeHtml(product.name) + '">'
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
                            '<span>' + (selectedQuantity > 0 ? 'x' + selectedQuantity + ' en pedido' : 'Tocar para agregar') + '</span>' +
                        '</div>' +
                    '</div>' +
                '</button>';
        }).join('');
    };

    const renderDraft = () => {
        if (!draftItemsContainer || !draftInputsContainer || !draftUnitsCount || !draftUniqueItemsChip || !draftSubtotal || !emptyDraftState) {
            return;
        }

        const entries = Array.from(selectedItems.values()).sort((left, right) => {
            return left.product.catalogIndex - right.product.catalogIndex;
        });

        const units = entries.reduce((total, entry) => total + entry.quantity, 0);
        const subtotal = entries.reduce((total, entry) => total + (entry.product.price * entry.quantity), 0);

        draftUnitsCount.textContent = String(units);
        draftUniqueItemsChip.textContent = entries.length + (entries.length === 1 ? ' referencia' : ' referencias');
        draftSubtotal.textContent = money(subtotal);
        emptyDraftState.style.display = entries.length === 0 ? '' : 'none';
        draftItemsContainer.innerHTML = entries.map(entry => {
            const mediaMarkup = entry.product.imageUrl
                ? '<img src="' + escapeHtml(entry.product.imageUrl) + '" alt="' + escapeHtml(entry.product.name) + '">'
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

        if (submitOrderButton) {
            submitOrderButton.disabled = entries.length === 0;
        }
    };

    orderServiceForm.addEventListener('submit', async function (event) {
        event.preventDefault();

        if (selectedItems.size === 0) {
            if (window.Swal) {
                await Swal.fire({
                    icon: 'warning',
                    title: 'Falta el pedido',
                    text: 'Selecciona al menos un producto de la carta antes de guardar.',
                    confirmButtonText: 'Aceptar',
                    confirmButtonColor: '#2563eb',
                });
            } else {
                alert('Selecciona al menos un producto de la carta antes de guardar.');
            }

            return;
        }

        const originalLabel = submitOrderButton ? submitOrderButton.innerHTML : '';

        if (submitOrderButton) {
            submitOrderButton.disabled = true;
            submitOrderButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
        }

        try {
            const response = await fetch(orderServiceForm.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: new FormData(orderServiceForm),
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                const validationMessages = data?.errors
                    ? Object.values(data.errors).flat().join('\n')
                    : (data?.message || 'No se pudo guardar el pedido.');

                if (window.Swal) {
                    await Swal.fire({
                        icon: 'error',
                        title: 'No se pudo guardar',
                        text: validationMessages,
                        confirmButtonText: 'Aceptar',
                        confirmButtonColor: '#dc3545',
                    });
                } else {
                    alert(validationMessages);
                }

                return;
            }

            if (data?.printUrl) {
                window.open(data.printUrl, '_blank', 'noopener,noreferrer');
            }

            if (window.Swal) {
                await Swal.fire({
                    icon: 'success',
                    title: 'Pedido enviado a cocina',
                    text: data?.message || 'El pedido se guardo correctamente.',
                    confirmButtonText: 'Aceptar',
                    confirmButtonColor: '#16a34a',
                });
            } else {
                alert(data?.message || 'El pedido se guardo correctamente.');
            }

            window.location.reload();
        } catch (error) {
            if (window.Swal) {
                await Swal.fire({
                    icon: 'error',
                    title: 'Error inesperado',
                    text: 'No se pudo guardar el pedido. Intenta nuevamente.',
                    confirmButtonText: 'Aceptar',
                    confirmButtonColor: '#dc3545',
                });
            } else {
                alert('No se pudo guardar el pedido. Intenta nuevamente.');
            }
        } finally {
            if (submitOrderButton) {
                submitOrderButton.disabled = selectedItems.size === 0;
                submitOrderButton.innerHTML = originalLabel;
            }
        }
    });

    if (productSearchInput) {
        productSearchInput.addEventListener('input', renderProductGrid);
    }

    categoryTabs.forEach(tab => {
        tab.addEventListener('click', function () {
            setActiveCategory(tab.dataset.categoryTab);
        });
    });

    if (productGrid) {
        productGrid.addEventListener('click', function (event) {
            const card = event.target.closest('[data-add-product]');

            if (!card) {
                return;
            }

            changeItemQuantity(card.dataset.addProduct, 1);
        });
    }

    if (draftItemsContainer) {
        draftItemsContainer.addEventListener('click', function (event) {
            const control = event.target.closest('[data-qty-product]');

            if (!control) {
                return;
            }

            changeItemQuantity(control.dataset.qtyProduct, Number(control.dataset.qtyChange || 0));
        });
    }

    seedDraftItems();
    renderDraft();
    setActiveCategory(categories[0]?.id || 'all');
});
</script>
@endif
@endsection
