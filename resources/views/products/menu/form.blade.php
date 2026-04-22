@extends('layouts.app')

@section('title', $pageTitle . ' - RestaurantePOS')

@section('content')
    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Gestion de Productos / RF-20</span>
                <h1>{{ $pageTitle }}</h1>
                <p>Configura nombre, categoria, precio e impuesto del producto. El stock en POS es opcional y puede quedar desactivado para platos o comidas.</p>
            </div>
            <div class="summary-group">
                <a href="{{ route('products.menu.index') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Volver al menu
                </a>
            </div>
        </section>

        @include('products.partials.form-errors')

        <div class="card module-card">
            <div class="card-body">
                <form method="POST" action="{{ $formAction }}" enctype="multipart/form-data">
                    @csrf
                    @if($product->exists)
                        @method('PUT')
                    @endif

                    <div class="form-grid">
                        <div>
                            <label class="form-label" for="name">Nombre</label>
                            <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $product->name) }}" required>
                        </div>

                        <div>
                            <label class="form-label" for="sku">SKU</label>
                            <input type="text" class="form-control" id="sku" name="sku" value="{{ old('sku', $product->sku) }}" required>
                        </div>

                        <div>
                            <label class="form-label" for="category_name">Categoria</label>
                            <input type="text" class="form-control" id="category_name" name="category_name" value="{{ $categoryName }}" list="category-options" required>
                            <datalist id="category-options">
                                @foreach($categoryOptions as $categoryOption)
                                    <option value="{{ $categoryOption->name }}"></option>
                                @endforeach
                            </datalist>
                            <div class="form-help">Puedes seleccionar una existente o escribir una nueva.</div>
                        </div>

                        <div>
                            <label class="form-label" for="tax_rate_id">Impuesto</label>
                            <select class="form-select" id="tax_rate_id" name="tax_rate_id">
                                <option value="">Sin impuesto</option>
                                @foreach($taxRates as $taxRate)
                                    <option value="{{ $taxRate->id }}" @selected((string) old('tax_rate_id', $product->tax_rate_id) === (string) $taxRate->id)>
                                        {{ $taxRate->name }} ({{ number_format($taxRate->rate, 2) }}%){{ $taxRate->is_default ? ' - Predeterminado' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="form-label" for="price">Precio</label>
                            <input type="number" class="form-control" id="price" name="price" min="0" step="0.01" value="{{ old('price', $product->price) }}" required>
                        </div>

                        <div class="form-switch-row full-width">
                            <div>
                                <label class="form-label d-block mb-1" for="tracks_stock">Control de stock</label>
                                <div class="form-help">Desactivalo para platos o comidas preparadas. Activalo solo si este producto debe descontar existencias en caja.</div>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="tracks_stock" name="tracks_stock" value="1" @checked(old('tracks_stock', $product->exists ? $product->tracks_stock : false))>
                                <label class="form-check-label" for="tracks_stock">Controlar stock en POS</label>
                            </div>
                        </div>

                        <div>
                            <label class="form-label" for="stock">Stock disponible</label>
                            <input type="number" class="form-control" id="stock" name="stock" min="0" step="1" value="{{ old('stock', $product->stock ?? 0) }}">
                            <div class="form-help" id="stockHelp">Usalo para bebidas u otros productos que si deben controlar unidades disponibles.</div>
                        </div>

                        <div class="form-switch-row full-width">
                            <div>
                                <label class="form-label d-block mb-1" for="active">Disponibilidad</label>
                                <div class="form-help">Si lo desactivas, dejara de aparecer en el POS.</div>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="active" name="active" value="1" @checked(old('active', $product->exists ? $product->active : true))>
                                <label class="form-check-label" for="active">Producto activo</label>
                            </div>
                        </div>

                        <div class="full-width">
                            <label class="form-label" for="description">Descripcion</label>
                            <textarea class="form-control" id="description" name="description" rows="4">{{ old('description', $product->description) }}</textarea>
                        </div>

                        <div class="full-width">
                            <label class="form-label" for="image">Imagen del producto</label>
                            <input type="file" class="form-control" id="image" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                            <div class="form-help">Sube una imagen referencial del producto. Formatos permitidos: JPG, PNG o WEBP.</div>

                            @if($product->image_url)
                                <div class="mt-3 d-flex flex-wrap align-items-start gap-3">
                                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" style="width: 160px; height: 160px; object-fit: cover; border-radius: 18px; border: 1px solid #dbe3f1;">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image" value="1">
                                        <label class="form-check-label" for="remove_image">Quitar imagen actual</label>
                                    </div>
                                </div>
                            @endif

                            <div class="mt-3" id="imagePreviewWrapper" style="display: none;">
                                <div class="table-note mb-2">Vista previa</div>
                                <img id="imagePreview" alt="Vista previa del producto" style="width: 160px; height: 160px; object-fit: cover; border-radius: 18px; border: 1px solid #dbe3f1;">
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="{{ route('products.menu.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tracksStockToggle = document.getElementById('tracks_stock');
            const stockInput = document.getElementById('stock');
            const stockHelp = document.getElementById('stockHelp');
            const imageInput = document.getElementById('image');
            const imagePreview = document.getElementById('imagePreview');
            const imagePreviewWrapper = document.getElementById('imagePreviewWrapper');

            function syncStockField() {
                const tracksStock = tracksStockToggle.checked;

                stockInput.disabled = !tracksStock;
                stockInput.required = tracksStock;
                stockHelp.textContent = tracksStock
                    ? 'Este producto descontara stock cada vez que se venda desde el POS.'
                    : 'Este producto se vendera sin controlar stock en POS, ideal para platos o comidas preparadas.';
            }

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
        });
    </script>
@endsection
