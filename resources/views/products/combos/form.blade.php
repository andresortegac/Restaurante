@extends('layouts.app')

@section('title', $pageTitle . ' - RestaurantePOS')

@section('content')
    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Gestion de Productos / RF-21</span>
                <h1>{{ $pageTitle }}</h1>
                <p>Configura el producto principal del combo y define los componentes que lo conforman. El stock del combo es opcional y normalmente puede quedar desactivado.</p>
            </div>
            <div class="summary-group">
                <a href="{{ route('products.combos.index') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Volver a combos
                </a>
            </div>
        </section>

        @include('products.partials.form-errors')

        <div class="card module-card">
            <div class="card-body">
                <form method="POST" action="{{ $formAction }}" enctype="multipart/form-data">
                    @csrf
                    @if($combo->exists)
                        @method('PUT')
                    @endif

                    <div class="form-grid">
                        <div>
                            <label class="form-label" for="name">Nombre del combo</label>
                            <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $combo->name) }}" required>
                        </div>

                        <div>
                            <label class="form-label" for="sku">SKU</label>
                            <input type="text" class="form-control" id="sku" name="sku" value="{{ old('sku', $combo->sku) }}" required>
                        </div>

                        <div>
                            <label class="form-label" for="category_name">Categoria</label>
                            <input type="text" class="form-control" id="category_name" name="category_name" value="{{ $categoryName }}" list="combo-category-options" required>
                            <datalist id="combo-category-options">
                                @foreach($categoryOptions as $categoryOption)
                                    <option value="{{ $categoryOption->name }}"></option>
                                @endforeach
                            </datalist>
                        </div>

                        <div>
                            <label class="form-label" for="tax_rate_id">Impuesto</label>
                            <select class="form-select" id="tax_rate_id" name="tax_rate_id">
                                <option value="">Sin impuesto</option>
                                @foreach($taxRates as $taxRate)
                                    <option value="{{ $taxRate->id }}" @selected((string) old('tax_rate_id', $combo->tax_rate_id) === (string) $taxRate->id)>
                                        {{ $taxRate->name }} ({{ number_format($taxRate->rate, 2) }}%)
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="form-label" for="price">Precio del combo</label>
                            <input type="number" class="form-control" id="price" name="price" min="0" step="0.01" value="{{ old('price', $combo->price) }}" required>
                        </div>

                        <div class="form-switch-row full-width">
                            <div>
                                <label class="form-label d-block mb-1" for="tracks_stock">Control de stock</label>
                                <div class="form-help">En la mayoria de los casos un combo no necesita stock propio porque depende de sus componentes o del inventario de cocina.</div>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="tracks_stock" name="tracks_stock" value="1" @checked(old('tracks_stock', $combo->exists ? $combo->tracks_stock : false))>
                                <label class="form-check-label" for="tracks_stock">Controlar stock en POS</label>
                            </div>
                        </div>

                        <div>
                            <label class="form-label" for="stock">Stock disponible</label>
                            <input type="number" class="form-control" id="stock" name="stock" min="0" step="1" value="{{ old('stock', $combo->stock ?? 0) }}">
                            <div class="form-help" id="stockHelp">Activala solo si realmente quieres limitar la venta del combo por unidades disponibles.</div>
                        </div>

                        <div class="form-switch-row full-width">
                            <div>
                                <label class="form-label d-block mb-1" for="active">Disponibilidad</label>
                                <div class="form-help">Si lo desactivas, el combo dejara de mostrarse en el POS.</div>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="active" name="active" value="1" @checked(old('active', $combo->exists ? $combo->active : true))>
                                <label class="form-check-label" for="active">Combo activo</label>
                            </div>
                        </div>

                        <div class="full-width">
                            <label class="form-label" for="description">Descripcion</label>
                            <textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $combo->description) }}</textarea>
                        </div>

                        <div class="full-width">
                            <label class="form-label" for="image">Imagen del producto</label>
                            <input type="file" class="form-control" id="image" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                            <div class="form-help">Puedes usar una imagen promocional o referencial del combo.</div>

                            @if($combo->image_url)
                                <div class="mt-3 d-flex flex-wrap align-items-start gap-3">
                                    <img src="{{ $combo->image_url }}" alt="{{ $combo->name }}" style="width: 160px; height: 160px; object-fit: cover; border-radius: 18px; border: 1px solid #dbe3f1;">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image" value="1">
                                        <label class="form-check-label" for="remove_image">Quitar imagen actual</label>
                                    </div>
                                </div>
                            @endif

                            <div class="mt-3" id="imagePreviewWrapper" style="display: none;">
                                <div class="table-note mb-2">Vista previa</div>
                                <img id="imagePreview" alt="Vista previa del combo" style="width: 160px; height: 160px; object-fit: cover; border-radius: 18px; border: 1px solid #dbe3f1;">
                            </div>
                        </div>
                    </div>

                    <div class="combo-section">
                        <div class="section-header">
                            <div>
                                <h5 class="mb-1">Componentes del combo</h5>
                                <p class="table-note mb-0">Agrega los productos simples que forman parte del combo.</p>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="addComponentRow">
                                <i class="fas fa-plus"></i> Agregar componente
                            </button>
                        </div>

                        <div id="comboComponentRows" class="component-rows">
                            @foreach($componentRows as $index => $component)
                                <div class="component-row" data-component-row>
                                    <div class="component-row-grid">
                                        <div class="component-field-large">
                                            <label class="form-label">Producto</label>
                                            <select class="form-select" name="components[{{ $index }}][component_product_id]">
                                                <option value="">Selecciona un producto</option>
                                                @foreach($availableProducts as $availableProduct)
                                                    <option value="{{ $availableProduct->id }}" @selected((string) ($component['component_product_id'] ?? '') === (string) $availableProduct->id)>
                                                        {{ $availableProduct->name }} ({{ $availableProduct->sku }}){{ $availableProduct->active ? '' : ' - Inactivo' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div>
                                            <label class="form-label">Cantidad</label>
                                            <input type="number" class="form-control" step="0.01" min="0.01" name="components[{{ $index }}][quantity]" value="{{ $component['quantity'] ?? 1 }}">
                                        </div>

                                        <div>
                                            <label class="form-label">Unidad</label>
                                            <input type="text" class="form-control" maxlength="50" name="components[{{ $index }}][unit_label]" value="{{ $component['unit_label'] ?? 'unidad' }}">
                                        </div>

                                        <div>
                                            <label class="form-label">Extra</label>
                                            <input type="number" class="form-control" step="0.01" min="0" name="components[{{ $index }}][extra_price]" value="{{ $component['extra_price'] ?? 0 }}">
                                        </div>
                                    </div>

                                    <div class="component-row-footer">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="component_optional_{{ $index }}" name="components[{{ $index }}][is_optional]" value="1" @checked(!empty($component['is_optional']))>
                                            <label class="form-check-label" for="component_optional_{{ $index }}">Componente opcional</label>
                                        </div>

                                        <button type="button" class="btn btn-outline-danger btn-sm" data-remove-component>Quitar</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="{{ route('products.combos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <template id="componentRowTemplate">
        <div class="component-row" data-component-row>
            <div class="component-row-grid">
                <div class="component-field-large">
                    <label class="form-label">Producto</label>
                    <select class="form-select" name="components[__INDEX__][component_product_id]">
                        <option value="">Selecciona un producto</option>
                        @foreach($availableProducts as $availableProduct)
                            <option value="{{ $availableProduct->id }}">
                                {{ $availableProduct->name }} ({{ $availableProduct->sku }}){{ $availableProduct->active ? '' : ' - Inactivo' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="form-label">Cantidad</label>
                    <input type="number" class="form-control" step="0.01" min="0.01" name="components[__INDEX__][quantity]" value="1">
                </div>

                <div>
                    <label class="form-label">Unidad</label>
                    <input type="text" class="form-control" maxlength="50" name="components[__INDEX__][unit_label]" value="unidad">
                </div>

                <div>
                    <label class="form-label">Extra</label>
                    <input type="number" class="form-control" step="0.01" min="0" name="components[__INDEX__][extra_price]" value="0">
                </div>
            </div>

            <div class="component-row-footer">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="component_optional___INDEX__" name="components[__INDEX__][is_optional]" value="1">
                    <label class="form-check-label" for="component_optional___INDEX__">Componente opcional</label>
                </div>

                <button type="button" class="btn btn-outline-danger btn-sm" data-remove-component>Quitar</button>
            </div>
        </div>
    </template>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const container = document.getElementById('comboComponentRows');
            const template = document.getElementById('componentRowTemplate');
            const addButton = document.getElementById('addComponentRow');
            const tracksStockToggle = document.getElementById('tracks_stock');
            const stockInput = document.getElementById('stock');
            const stockHelp = document.getElementById('stockHelp');
            const imageInput = document.getElementById('image');
            const imagePreview = document.getElementById('imagePreview');
            const imagePreviewWrapper = document.getElementById('imagePreviewWrapper');
            let nextIndex = {{ count($componentRows) }};

            function syncStockField() {
                const tracksStock = tracksStockToggle.checked;

                stockInput.disabled = !tracksStock;
                stockInput.required = tracksStock;
                stockHelp.textContent = tracksStock
                    ? 'Este combo descontara stock cada vez que se venda desde el POS.'
                    : 'Este combo se vendera sin controlar stock propio en POS.';
            }

            addButton.addEventListener('click', function () {
                const html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex++));
                container.insertAdjacentHTML('beforeend', html);
            });

            tracksStockToggle.addEventListener('change', syncStockField);
            syncStockField();

            if (imageInput && imagePreview && imagePreviewWrapper) {
                imageInput.addEventListener('change', function () {
                    const [file] = imageInput.files || [];

                    if (!file) {
                        imagePreviewWrapper.style.display = 'none';
                        imagePreview.removeAttribute('src');
                        return;
                    }

                    imagePreview.src = URL.createObjectURL(file);
                    imagePreviewWrapper.style.display = 'block';
                });
            }

            container.addEventListener('click', function (event) {
                const removeButton = event.target.closest('[data-remove-component]');

                if (!removeButton) {
                    return;
                }

                const row = removeButton.closest('[data-component-row]');

                if (row) {
                    row.remove();
                }
            });
        });
    </script>
@endsection
