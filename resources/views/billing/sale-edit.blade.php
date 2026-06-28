@extends('layouts.app')

@section('title', 'Editar factura #' . $sale->id)

@section('content')
    @php
        $isLocked = ! $canAdjustSale;
        $oldRows = old('items');
        $editableItems = $sale->items->filter(fn ($item) => $item->product_id);
        $currentQuantities = $editableItems
            ->groupBy('product_id')
            ->map(fn ($items) => (int) $items->sum('quantity'));
        $deliveryFee = (float) $sale->items
            ->filter(fn ($item) => ! $item->product_id && $item->product_name === 'Costo domicilio')
            ->sum('subtotal');

        $productCatalog = $products->values()->map(fn ($product, $index) => [
            'id' => (int) $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'price' => (float) $product->price,
            'catalogIndex' => (int) $index,
            'imageUrl' => $product->image_url,
            'categoryId' => $product->category_id ? 'category-' . $product->category_id : 'uncategorized',
            'categoryName' => $product->menuCategory->name ?? 'Sin categoria',
            'searchText' => \Illuminate\Support\Str::lower(trim(implode(' ', [
                $product->name,
                $product->description,
                $product->menuCategory->name ?? 'Sin categoria',
            ]))),
        ])->values();

        $menuCategories = $products
            ->groupBy(fn ($product) => $product->category_id ? 'category-' . $product->category_id : 'uncategorized')
            ->map(function ($categoryProducts, $categoryKey) {
                $firstProduct = $categoryProducts->first();

                return [
                    'id' => $categoryKey,
                    'name' => $firstProduct->menuCategory->name ?? 'Sin categoria',
                    'description' => $firstProduct->menuCategory->description ?? 'Productos disponibles.',
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
                ->values();
        }
    @endphp

    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Facturacion / Edicion</span>
                <h1>Editar factura {{ $sale->invoice?->invoice_number ?? '#' . $sale->id }}</h1>
                <p>{{ $sale->delivery ? 'Domicilio ' . $sale->delivery->delivery_number : 'Cobro manual' }}</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">${{ money($sale->total) }} actual</span>
                <span class="summary-chip">{{ $canAdjustSale ? 'Caja abierta' : 'Caja cerrada' }}</span>
                <a href="{{ route('billing.history') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>
        </section>

        @include('products.partials.form-errors')

        @if($isLocked)
            <div class="alert alert-warning">
                Esta factura pertenece a una caja cerrada. Puedes revisarla, pero no guardarla porque alteraria un cierre anterior.
            </div>
        @endif

        <form method="POST" action="{{ route('billing.sales.update', $sale) }}" id="saleEditForm">
            @csrf
            @method('PUT')

            <aside class="waiter-draft-shell order-draft-before-products mb-4">
                <div class="waiter-draft-header">
                    <div>
                        <div class="summary-kicker">Resumen en tiempo real</div>
                        <h6 class="mb-1">Factura actualizada</h6>
                    </div>
                    <span class="summary-chip" id="saleDraftUniqueItemsChip">0 referencias</span>
                </div>

                <div class="waiter-draft-stats">
                    <div class="meta-box">
                        <div class="summary-kicker">Unidades</div>
                        <div class="fw-bold" id="saleDraftUnitsCount">0</div>
                    </div>
                    <div class="meta-box">
                        <div class="summary-kicker">Total productos</div>
                        <div class="fw-bold" id="saleDraftSubtotal">$0</div>
                    </div>
                    <div class="meta-box">
                        <div class="summary-kicker">Diferencia</div>
                        <div class="fw-bold" id="saleDraftDelta">$0</div>
                    </div>
                </div>

                @if($deliveryFee > 0)
                    <div class="meta-box mb-3">
                        <div class="summary-kicker">Domicilio</div>
                        <div class="fw-bold">Se conserva el costo de domicilio: ${{ money($deliveryFee) }}</div>
                    </div>
                @endif

                <div class="waiter-empty-draft meta-box" id="saleEmptyDraft">La factura debe conservar al menos un producto.</div>
                <div class="waiter-draft-items" id="saleDraftItems"></div>
                <div id="saleItemsInputs"></div>

                <div class="form-grid waiter-order-meta-grid mt-3">
                    <div class="order-notes-field">
                        <label class="form-label" for="notes">Notas</label>
                        <textarea class="form-control order-notes-textarea" id="notes" name="notes" rows="4" @disabled($isLocked)>{{ old('notes', $sale->notes) }}</textarea>
                    </div>
                </div>

                <div class="form-actions waiter-form-actions">
                    <a href="{{ route('billing.history') }}" class="btn btn-outline-secondary">Volver</a>
                    <button type="submit" class="btn btn-primary" id="submitSaleEditButton" @disabled($isLocked)>
                        <i class="fas fa-save"></i> Guardar cambios
                    </button>
                </div>
            </aside>

            <div class="card module-card service-card">
                <div class="card-body">
                    <div class="waiter-menu-shell">
                        <div class="waiter-menu-toolbar">
                            <div>
                                <div class="summary-kicker">Productos</div>
                                <h6 class="mb-1">Selecciona productos</h6>
                            </div>
                            <div class="waiter-menu-search waiter-menu-search-emphasis">
                                <label class="form-label" for="saleProductSearch">Filtrar por nombre</label>
                                <input type="search" class="form-control" id="saleProductSearch" placeholder="Ej: churrasco, limonada, postre">
                            </div>
                        </div>

                        <div class="menu-category-tabs">
                            <button type="button" class="menu-category-tab is-active" data-sale-category-tab="all">
                                Todo el menu <span>{{ $products->count() }}</span>
                            </button>
                            @foreach($menuCategories as $menuCategory)
                                <button type="button" class="menu-category-tab" data-sale-category-tab="{{ $menuCategory['id'] }}">
                                    {{ $menuCategory['name'] }} <span>{{ $menuCategory['count'] }}</span>
                                </button>
                            @endforeach
                        </div>
                        <p class="menu-category-caption" id="saleCategoryCaption"></p>
                        <div class="waiter-menu-groups" id="saleProductGrid"></div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const products = @json($productCatalog);
        const categories = @json($menuCategories);
        const initialDraftItems = @json($initialDraftItems);
        const originalTotal = Number(@json((float) $sale->total - $deliveryFee));
        const isLocked = @json($isLocked);
        const productMap = new Map(products.map(product => [String(product.id), product]));
        const selectedItems = new Map();
        let activeCategory = 'all';

        const productSearch = document.getElementById('saleProductSearch');
        const productGrid = document.getElementById('saleProductGrid');
        const draftItems = document.getElementById('saleDraftItems');
        const inputs = document.getElementById('saleItemsInputs');
        const emptyDraft = document.getElementById('saleEmptyDraft');
        const unitsLabel = document.getElementById('saleDraftUnitsCount');
        const uniqueLabel = document.getElementById('saleDraftUniqueItemsChip');
        const subtotalLabel = document.getElementById('saleDraftSubtotal');
        const deltaLabel = document.getElementById('saleDraftDelta');
        const submitButton = document.getElementById('submitSaleEditButton');
        const categoryTabs = Array.from(document.querySelectorAll('[data-sale-category-tab]'));
        const categoryCaption = document.getElementById('saleCategoryCaption');

        const escapeHtml = value => String(value || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        const normalizeText = value => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
        const money = value => '$' + Math.round(Number(value || 0)).toLocaleString('es-CO');
        const placeholderMarkup = icon => '<div class="waiter-image-placeholder"><i class="' + icon + '"></i></div>';
        const findCategory = categoryId => categories.find(category => category.id === categoryId);

        const seedDraftItems = () => {
            initialDraftItems.forEach(item => {
                const product = productMap.get(String(item.productId));
                if (product && Number(item.quantity || 0) > 0) {
                    selectedItems.set(String(product.id), { product, quantity: Number(item.quantity) });
                }
            });
        };

        const filteredProducts = () => {
            const search = normalizeText(productSearch?.value).trim();
            return products.filter(product => {
                const categoryMatch = activeCategory === 'all' || product.categoryId === activeCategory;
                const searchMatch = search === '' || normalizeText(product.searchText).includes(search);
                return categoryMatch && searchMatch;
            });
        };

        const changeItemQuantity = (productId, delta) => {
            if (isLocked) return;
            const key = String(productId);
            const entry = selectedItems.get(key);

            if (!entry) {
                const product = productMap.get(key);
                if (product && delta > 0) selectedItems.set(key, { product, quantity: delta });
                render();
                return;
            }

            const nextQuantity = entry.quantity + delta;
            if (nextQuantity <= 0) selectedItems.delete(key);
            else selectedItems.set(key, { product: entry.product, quantity: nextQuantity });
            render();
        };

        const productCard = product => {
            const selectedQuantity = selectedItems.get(String(product.id))?.quantity || 0;
            const media = product.imageUrl ? '<img src="' + escapeHtml(product.imageUrl) + '" alt="' + escapeHtml(product.name) + '">' : placeholderMarkup('fas fa-utensils');
            return '<button type="button" class="waiter-menu-card" data-sale-product="' + product.id + '"' + (isLocked ? ' disabled' : '') + '>' +
                '<div class="waiter-menu-card-media">' + media + '</div>' +
                '<div class="waiter-menu-card-copy"><div class="waiter-product-heading"><div><h6>' + escapeHtml(product.name) + '</h6><div class="table-note">' + escapeHtml(product.categoryName) + '</div></div><span class="waiter-type-pill">Producto</span></div>' +
                '<p>' + escapeHtml(product.description || 'Sin descripcion adicional.') + '</p><div class="waiter-product-footer"><strong>' + money(product.price) + '</strong><span>' + (selectedQuantity > 0 ? 'x' + selectedQuantity + ' en factura' : 'Agregar') + '</span></div></div></button>';
        };

        const renderProductGrid = () => {
            const visible = filteredProducts();
            if (!visible.length) {
                productGrid.innerHTML = '<div class="meta-box waiter-empty-grid">No encontramos productos para ese filtro.</div>';
                return;
            }
            if ((productSearch?.value || '').trim() !== '') {
                productGrid.innerHTML = '<div class="waiter-menu-grid">' + visible.map(productCard).join('') + '</div>';
                return;
            }
            productGrid.innerHTML = categories.map(category => {
                const rows = visible.filter(product => product.categoryId === category.id);
                if (!rows.length) return '';
                return '<section class="waiter-menu-category-group"><div class="waiter-menu-category-heading"><div><div class="summary-kicker">' + escapeHtml(category.name) + '</div><p class="table-note mb-0">' + escapeHtml(category.description || '') + '</p></div><span class="summary-chip">' + rows.length + ' productos</span></div><div class="waiter-menu-grid">' + rows.map(productCard).join('') + '</div></section>';
            }).join('');
        };

        const renderDraft = () => {
            const rows = Array.from(selectedItems.values()).sort((a, b) => a.product.catalogIndex - b.product.catalogIndex);
            const units = rows.reduce((sum, row) => sum + row.quantity, 0);
            const subtotal = rows.reduce((sum, row) => sum + row.product.price * row.quantity, 0);
            const delta = subtotal - originalTotal;
            unitsLabel.textContent = String(units);
            uniqueLabel.textContent = rows.length + (rows.length === 1 ? ' referencia' : ' referencias');
            subtotalLabel.textContent = money(subtotal);
            deltaLabel.textContent = (delta > 0 ? '+' : delta < 0 ? '-' : '') + money(Math.abs(delta));
            deltaLabel.classList.toggle('text-success', delta > 0);
            deltaLabel.classList.toggle('text-danger', delta < 0);
            emptyDraft.style.display = rows.length === 0 ? '' : 'none';
            draftItems.innerHTML = rows.map(row => {
                const media = row.product.imageUrl ? '<img src="' + escapeHtml(row.product.imageUrl) + '" alt="' + escapeHtml(row.product.name) + '">' : placeholderMarkup('fas fa-image');
                return '<article class="waiter-draft-item"><div class="waiter-draft-thumb">' + media + '</div><div class="waiter-draft-copy"><div class="d-flex justify-content-between gap-3 align-items-start"><div><h6 class="mb-1">' + escapeHtml(row.product.name) + '</h6><div class="table-note">' + money(row.product.price) + '</div></div><button type="button" class="btn btn-link text-danger p-0 waiter-remove-line" data-sale-qty-product="' + row.product.id + '" data-sale-qty-change="' + (-row.quantity) + '"' + (isLocked ? ' disabled' : '') + '>Quitar</button></div><div class="waiter-draft-controls"><div class="waiter-qty-controls"><button type="button" class="btn btn-outline-secondary btn-sm" data-sale-qty-product="' + row.product.id + '" data-sale-qty-change="-1"' + (isLocked ? ' disabled' : '') + '>-</button><span>' + row.quantity + '</span><button type="button" class="btn btn-outline-secondary btn-sm" data-sale-qty-product="' + row.product.id + '" data-sale-qty-change="1"' + (isLocked ? ' disabled' : '') + '>+</button></div><strong>' + money(row.product.price * row.quantity) + '</strong></div></div></article>';
            }).join('');
            inputs.innerHTML = rows.map((row, index) => '<input type="hidden" name="items[' + index + '][product_id]" value="' + row.product.id + '"><input type="hidden" name="items[' + index + '][quantity]" value="' + row.quantity + '">').join('');
            submitButton.disabled = isLocked || rows.length === 0;
        };

        const render = () => {
            renderDraft();
            renderProductGrid();
        };

        categoryTabs.forEach(tab => tab.addEventListener('click', function () {
            activeCategory = tab.dataset.saleCategoryTab;
            categoryTabs.forEach(item => item.classList.toggle('is-active', item === tab));
            const category = activeCategory === 'all' ? null : findCategory(activeCategory);
            categoryCaption.textContent = category ? category.description : '';
            renderProductGrid();
        }));
        productSearch?.addEventListener('input', renderProductGrid);
        productGrid?.addEventListener('click', event => {
            const card = event.target.closest('[data-sale-product]');
            if (card) changeItemQuantity(card.dataset.saleProduct, 1);
        });
        draftItems?.addEventListener('click', event => {
            const control = event.target.closest('[data-sale-qty-product]');
            if (control) changeItemQuantity(control.dataset.saleQtyProduct, Number(control.dataset.saleQtyChange || 0));
        });

        seedDraftItems();
        render();
    });
    </script>
@endsection
