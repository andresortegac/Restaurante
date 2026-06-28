@extends('layouts.app')

@section('title', 'Editar pedido ' . $order->order_number)

@section('content')
    @php
        $isPaid = (bool) $order->sale;
        $isLocked = $isPaid && ! $canAdjustPaidSale;
        $oldRows = old('items');
        $currentQuantities = $order->items
            ->groupBy('product_id')
            ->map(fn ($items) => (int) $items->sum('quantity'));

        $productCatalog = $products->values()->map(fn ($product, $index) => [
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

        $menuCategories = $products
            ->groupBy(fn ($product) => $product->category_id ? 'category-' . $product->category_id : 'uncategorized')
            ->map(function ($categoryProducts, $categoryKey) {
                $firstProduct = $categoryProducts->first();

                return [
                    'id' => $categoryKey,
                    'name' => $firstProduct->menuCategory->name ?? 'Sin categoria',
                    'description' => $firstProduct->menuCategory->description ?? 'Productos disponibles para el pedido.',
                    'count' => $categoryProducts->count(),
                ];
            })
            ->values();

        $initialDraftItems = collect(is_array($oldRows) ? $oldRows : [])
            ->map(fn ($row) => [
                'productId' => (int) ($row['product_id'] ?? 0),
                'quantity' => max(0, (int) ($row['quantity'] ?? 0)),
            ])
            ->filter(fn ($row) => $row['productId'] > 0 && $row['quantity'] > 0)
            ->values();

        if ($initialDraftItems->isEmpty()) {
            $initialDraftItems = $currentQuantities
                ->map(fn ($quantity, $productId) => [
                    'productId' => (int) $productId,
                    'quantity' => (int) $quantity,
                ])
                ->filter(fn ($row) => $row['productId'] > 0 && $row['quantity'] > 0)
                ->values();
        }
    @endphp

    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Pedidos / Edicion</span>
                <h1>Editar {{ $order->order_number }}</h1>
                <p>
                    {{ $order->table?->name ?? 'Pedido sin mesa' }}
                    @if($isPaid)
                        | Recibo {{ $order->sale?->invoice?->invoice_number ?? '#' . $order->sale?->id }}
                    @endif
                </p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">${{ money($order->total) }} actual</span>
                @if($isPaid)
                    <span class="summary-chip">{{ $canAdjustPaidSale ? 'Caja abierta' : 'Caja cerrada' }}</span>
                @endif
                <a href="{{ $order->table ? route('orders.show', $order->table) : route('orders.history.index') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>
        </section>

        @include('products.partials.form-errors')

        @if($isLocked)
            <div class="alert alert-warning">
                Este pedido ya pertenece a una caja cerrada. Puedes revisarlo, pero no guardarlo porque alteraria un cierre anterior.
            </div>
        @endif

        <form method="POST" action="{{ route('orders.update', $order) }}" id="orderEditForm">
            @csrf
            @method('PUT')

            <aside class="waiter-draft-shell order-draft-before-products mb-4">
                <div class="waiter-draft-header">
                    <div>
                        <div class="summary-kicker">Resumen en tiempo real</div>
                        <h6 class="mb-1">Pedido actualizado</h6>
                    </div>
                    <span class="summary-chip" id="editDraftUniqueItemsChip">0 referencias</span>
                </div>

                <div class="waiter-draft-stats">
                    <div class="meta-box">
                        <div class="summary-kicker">Unidades</div>
                        <div class="fw-bold" id="editDraftUnitsCount">0</div>
                    </div>
                    <div class="meta-box">
                        <div class="summary-kicker">Total</div>
                        <div class="fw-bold" id="editDraftSubtotal">$0</div>
                    </div>
                    <div class="meta-box">
                        <div class="summary-kicker">Diferencia</div>
                        <div class="fw-bold" id="editDraftDelta">$0</div>
                    </div>
                </div>

                <div class="waiter-empty-draft meta-box" id="editEmptyDraft">
                    El pedido debe conservar al menos un producto.
                </div>

                <div class="waiter-draft-items" id="editDraftItems"></div>
                <div id="editOrderItemsInputs"></div>

                <div class="form-grid waiter-order-meta-grid mt-3">
                    <div class="order-notes-field">
                        <label class="form-label" for="notes">Notas del pedido</label>
                        <textarea class="form-control order-notes-textarea" id="notes" name="notes" rows="4" @disabled($isLocked)>{{ old('notes', $order->notes) }}</textarea>
                    </div>
                </div>

                <div class="form-actions waiter-form-actions">
                    <a href="{{ $order->table ? route('orders.show', $order->table) : route('orders.history.index') }}" class="btn btn-outline-secondary">Volver</a>
                    <button type="submit" class="btn btn-primary" id="submitEditOrderButton" @disabled($isLocked)>
                        <i class="fas fa-save"></i> Guardar cambios
                    </button>
                </div>
            </aside>

            <div class="card module-card service-card" id="service-flow">
                <div class="card-header">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div>
                            <h5 class="mb-1">Carta del menu</h5>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="waiter-menu-shell">
                        <div class="waiter-menu-toolbar">
                            <div>
                                <div class="summary-kicker">Productos</div>
                                <h6 class="mb-1">Selecciona productos</h6>
                            </div>
                            <div class="waiter-menu-search waiter-menu-search-emphasis">
                                <label class="form-label" for="editMenuProductSearch">Filtrar por nombre</label>
                                <input type="search" class="form-control" id="editMenuProductSearch" placeholder="Ej: churrasco, limonada, postre">
                            </div>
                        </div>

                        <div class="menu-category-tabs">
                            <button type="button" class="menu-category-tab is-active" data-edit-category-tab="all">
                                Todo el menu <span>{{ $products->count() }}</span>
                            </button>
                            @foreach($menuCategories as $menuCategory)
                                <button type="button" class="menu-category-tab" data-edit-category-tab="{{ $menuCategory['id'] }}">
                                    {{ $menuCategory['name'] }} <span>{{ $menuCategory['count'] }}</span>
                                </button>
                            @endforeach
                        </div>
                        <p class="menu-category-caption" id="editMenuCategoryCaption"></p>
                        <div class="waiter-menu-groups" id="editProductGrid"></div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('orderEditForm');

        if (!form) {
            return;
        }

        const products = @json($productCatalog);
        const categories = @json($menuCategories);
        const initialDraftItems = @json($initialDraftItems);
        const originalTotal = Number(@json((float) $order->total));
        const isLocked = @json($isLocked);
        const productMap = new Map(products.map(product => [String(product.id), product]));
        const selectedItems = new Map();
        let activeCategory = 'all';

        const productSearchInput = document.getElementById('editMenuProductSearch');
        const productGrid = document.getElementById('editProductGrid');
        const draftItemsContainer = document.getElementById('editDraftItems');
        const draftInputsContainer = document.getElementById('editOrderItemsInputs');
        const emptyDraftState = document.getElementById('editEmptyDraft');
        const unitsCount = document.getElementById('editDraftUnitsCount');
        const uniqueItemsChip = document.getElementById('editDraftUniqueItemsChip');
        const subtotalLabel = document.getElementById('editDraftSubtotal');
        const deltaLabel = document.getElementById('editDraftDelta');
        const submitButton = document.getElementById('submitEditOrderButton');
        const categoryTabs = Array.from(document.querySelectorAll('[data-edit-category-tab]'));
        const categoryCaption = document.getElementById('editMenuCategoryCaption');

        const escapeHtml = value => String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
        const normalizeText = value => String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase();
        const money = value => '$' + Math.round(Number(value || 0)).toLocaleString('es-CO');
        const placeholderMarkup = icon => '<div class="waiter-image-placeholder"><i class="' + icon + '"></i></div>';
        const typeLabel = () => 'Producto';
        const findCategory = categoryId => categories.find(category => category.id === categoryId);

        const seedDraftItems = () => {
            initialDraftItems.forEach(item => {
                const product = productMap.get(String(item.productId));

                if (!product || !Number(item.quantity || 0)) {
                    return;
                }

                selectedItems.set(String(product.id), {
                    product,
                    quantity: Number(item.quantity),
                });
            });
        };

        const setActiveCategory = categoryId => {
            activeCategory = categoryId;

            categoryTabs.forEach(tab => {
                tab.classList.toggle('is-active', tab.dataset.editCategoryTab === categoryId);
            });

            const category = categoryId === 'all' ? null : findCategory(categoryId);

            if (categoryCaption) {
                categoryCaption.textContent = category ? category.description : '';
            }

            renderProductGrid();
        };

        const changeItemQuantity = (productId, delta) => {
            if (isLocked) {
                return;
            }

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
            const searchTerm = normalizeText(productSearchInput?.value).trim();
            const visibleProducts = products.filter(product => {
                const matchesCategory = activeCategory === 'all' || product.categoryId === activeCategory;
                const matchesSearch = searchTerm === '' || normalizeText(product.searchText).includes(searchTerm);

                return matchesCategory && matchesSearch;
            });

            if (searchTerm === '') {
                return visibleProducts;
            }

            return visibleProducts.sort((left, right) => {
                const leftName = normalizeText(left.name).trim();
                const rightName = normalizeText(right.name).trim();
                const leftStartsWithSearch = leftName.startsWith(searchTerm);
                const rightStartsWithSearch = rightName.startsWith(searchTerm);

                if (leftStartsWithSearch !== rightStartsWithSearch) {
                    return leftStartsWithSearch ? -1 : 1;
                }

                return left.catalogIndex - right.catalogIndex;
            });
        };

        const productCard = product => {
            const selectedQuantity = selectedItems.get(String(product.id))?.quantity || 0;
            const mediaMarkup = product.imageUrl
                ? '<img src="' + escapeHtml(product.imageUrl) + '" alt="' + escapeHtml(product.name) + '" loading="lazy" decoding="async">'
                : placeholderMarkup('fas fa-utensils');

            return '' +
                '<button type="button" class="waiter-menu-card" data-edit-add-product="' + product.id + '"' + (isLocked ? ' disabled' : '') + '>' +
                    '<div class="waiter-menu-card-media">' + mediaMarkup + '</div>' +
                    '<div class="waiter-menu-card-copy">' +
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
                            '<span>' + (selectedQuantity > 0 ? 'x' + selectedQuantity + ' en pedido' : 'Agregar') + '</span>' +
                        '</div>' +
                    '</div>' +
                '</button>';
        };

        const renderProductGrid = () => {
            if (!productGrid) {
                return;
            }

            const visibleProducts = filteredProducts();

            if (visibleProducts.length === 0) {
                productGrid.innerHTML = '<div class="meta-box waiter-empty-grid">No encontramos productos para ese filtro.</div>';
                return;
            }

            const searchTerm = (productSearchInput?.value || '').toString().trim();

            if (searchTerm !== '') {
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
                                '<p class="table-note mb-0">' + escapeHtml(category.description || '') + '</p>' +
                            '</div>' +
                            '<span class="summary-chip">' + categoryProducts.length + (categoryProducts.length === 1 ? ' producto' : ' productos') + '</span>' +
                        '</div>' +
                        '<div class="waiter-menu-grid">' + categoryProducts.map(productCard).join('') + '</div>' +
                    '</section>';
            }).join('');
        };

        const renderDraft = () => {
            const entries = Array.from(selectedItems.values()).sort((left, right) => left.product.catalogIndex - right.product.catalogIndex);
            const units = entries.reduce((total, entry) => total + entry.quantity, 0);
            const subtotal = entries.reduce((total, entry) => total + (entry.product.price * entry.quantity), 0);
            const delta = subtotal - originalTotal;

            unitsCount.textContent = String(units);
            uniqueItemsChip.textContent = entries.length + (entries.length === 1 ? ' referencia' : ' referencias');
            subtotalLabel.textContent = money(subtotal);
            deltaLabel.textContent = (delta > 0 ? '+' : delta < 0 ? '-' : '') + money(Math.abs(delta));
            deltaLabel.classList.toggle('text-success', delta > 0);
            deltaLabel.classList.toggle('text-danger', delta < 0);
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
                                    '<div class="table-note">' + money(entry.product.price) + '</div>' +
                                '</div>' +
                                '<button type="button" class="btn btn-link text-danger p-0 waiter-remove-line" data-edit-qty-product="' + entry.product.id + '" data-edit-qty-change="' + (-entry.quantity) + '"' + (isLocked ? ' disabled' : '') + '>Quitar</button>' +
                            '</div>' +
                            '<div class="waiter-draft-controls">' +
                                '<div class="waiter-qty-controls">' +
                                    '<button type="button" class="btn btn-outline-secondary btn-sm" data-edit-qty-product="' + entry.product.id + '" data-edit-qty-change="-1"' + (isLocked ? ' disabled' : '') + '>-</button>' +
                                    '<span>' + entry.quantity + '</span>' +
                                    '<button type="button" class="btn btn-outline-secondary btn-sm" data-edit-qty-product="' + entry.product.id + '" data-edit-qty-change="1"' + (isLocked ? ' disabled' : '') + '>+</button>' +
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

            if (submitButton) {
                submitButton.disabled = isLocked || entries.length === 0;
            }
        };

        form.addEventListener('submit', async function (event) {
            if (selectedItems.size > 0 && !isLocked) {
                return;
            }

            event.preventDefault();

            if (window.Swal) {
                await Swal.fire({
                    icon: 'warning',
                    title: isLocked ? 'Caja cerrada' : 'Falta el pedido',
                    text: isLocked ? 'Este pedido pertenece a un cierre anterior.' : 'El pedido debe conservar al menos un producto.',
                    confirmButtonText: 'Aceptar',
                    confirmButtonColor: '#2563eb',
                });
            }
        });

        if (productSearchInput) {
            productSearchInput.addEventListener('input', renderProductGrid);
        }

        categoryTabs.forEach(tab => {
            tab.addEventListener('click', function () {
                setActiveCategory(tab.dataset.editCategoryTab);
            });
        });

        if (productGrid) {
            productGrid.addEventListener('click', function (event) {
                const card = event.target.closest('[data-edit-add-product]');

                if (!card) {
                    return;
                }

                changeItemQuantity(card.dataset.editAddProduct, 1);
            });
        }

        if (draftItemsContainer) {
            draftItemsContainer.addEventListener('click', function (event) {
                const control = event.target.closest('[data-edit-qty-product]');

                if (!control) {
                    return;
                }

                changeItemQuantity(control.dataset.editQtyProduct, Number(control.dataset.editQtyChange || 0));
            });
        }

        seedDraftItems();
        renderDraft();
        setActiveCategory('all');
    });
    </script>
@endsection
