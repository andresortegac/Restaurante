@extends('layouts.app')

@section('title', 'Menu y Precios - RestaurantePOS')

@section('content')
    @php
        $user = Auth::user();
        $canCreateProduct = $user->hasRole('Admin') || $user->hasPermission('products.create');
        $canEditProduct = $user->hasRole('Admin') || $user->hasPermission('products.edit');
        $canDeleteProduct = $user->hasRole('Admin') || $user->hasPermission('products.delete');
        $canCreateCategory = $user->hasRole('Admin') || $user->hasPermission('products.create');
        $canEditCategory = $user->hasRole('Admin') || $user->hasPermission('products.edit');
        $canDeleteCategory = $user->hasRole('Admin') || $user->hasPermission('products.delete');

        $menuSections = $categories
            ->map(function ($category) use ($products) {
                return [
                    'key' => 'category-' . $category->id,
                    'title' => $category->name,
                    'description' => $category->description ?: 'Categoria disponible para el menu grafico.',
                    'is_active' => $category->is_active,
                    'products' => $products->where('category_id', $category->id)->values(),
                ];
            })
            ->filter(fn (array $section) => $section['products']->isNotEmpty())
            ->values();

        $uncategorizedProducts = $products->whereNull('category_id')->values();

        if ($uncategorizedProducts->isNotEmpty()) {
            $menuSections = $menuSections->push([
                'key' => 'uncategorized',
                'title' => 'Sin categoria',
                'description' => 'Productos pendientes por clasificar dentro del menu.',
                'is_active' => true,
                'products' => $uncategorizedProducts,
            ]);
        }
    @endphp

    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Gestion de Productos / RF-20</span>
                <h1>Menu grafico, categorias y orden visual</h1>
                <p>Administra la carta que vera el mesero: organiza categorias, define el orden de aparicion y mantén los productos listos para una seleccion tactil rapida.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">{{ $products->count() }} productos</span>
                <span class="summary-chip">{{ $categories->count() }} categorias</span>
                <span class="summary-chip">{{ $products->where('active', true)->count() }} activos</span>
                <span class="summary-chip">{{ $uncategorizedProductsCount }} sin categoria</span>
            </div>
        </section>

        @include('products.partials.form-errors')

        <div class="module-toolbar">
            <div>
                <h5 class="mb-1">Carta publicada</h5>
                <p class="table-note mb-0">La vista se ordena primero por categoria y luego por el orden visual de cada producto.</p>
            </div>
            @if($canCreateProduct)
                <a href="{{ route('products.menu.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nuevo producto
                </a>
            @endif
        </div>

        <div class="row g-4">
            <div class="col-xl-8">
                @if($menuSections->isEmpty())
                    <div class="card module-card">
                        <div class="card-body">
                            <div class="empty-state">
                                <i class="fas fa-book-open"></i>
                                <h5 class="mb-2">Todavia no hay productos publicados</h5>
                                <p class="mb-0">Crea productos y categorias para empezar a armar la carta grafica del restaurante.</p>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="menu-section-stack">
                        @foreach($menuSections as $section)
                            <section class="card module-card menu-showcase-card">
                                <div class="card-body">
                                    <div class="menu-showcase-header">
                                        <div>
                                            <div class="summary-kicker">Categoria</div>
                                            <h5 class="mb-1">{{ $section['title'] }}</h5>
                                            <p class="table-note mb-0">{{ $section['description'] }}</p>
                                        </div>
                                        <div class="menu-showcase-pills">
                                            <span class="summary-chip">{{ $section['products']->count() }} items</span>
                                            @if(!$section['is_active'])
                                                <span class="summary-chip menu-chip-muted">Oculta para meseros</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="menu-product-grid">
                                        @foreach($section['products'] as $product)
                                            <article class="menu-product-card {{ $product->active ? '' : 'is-inactive' }}">
                                                <div class="menu-product-media">
                                                    @if($product->image_url)
                                                        <img src="{{ $product->image_url }}" alt="{{ $product->name }}">
                                                    @else
                                                        <div class="menu-product-placeholder">
                                                            <i class="fas fa-utensils"></i>
                                                        </div>
                                                    @endif
                                                </div>

                                                <div class="menu-product-body">
                                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                                        <div>
                                                            <h6 class="mb-1">{{ $product->name }}</h6>
                                                            <div class="table-note">{{ $product->sku }}</div>
                                                        </div>
                                                        <span class="badge rounded-pill {{ $product->active ? 'bg-success' : 'bg-secondary' }}">
                                                            {{ $product->active ? 'Activo' : 'Inactivo' }}
                                                        </span>
                                                    </div>

                                                    <p class="menu-product-description">{{ $product->description ?: 'Sin descripcion registrada para este producto.' }}</p>

                                                    <div class="menu-product-meta">
                                                        <span>Orden {{ $product->sort_order }}</span>
                                                        <span>{{ $product->taxRate->name ?? 'Sin impuesto' }}</span>
                                                        <span>{{ $product->tracks_stock ? 'Stock: ' . $product->stock : 'Sin control de stock' }}</span>
                                                    </div>

                                                    <div class="menu-product-footer">
                                                        <div>
                                                            <div class="summary-kicker">Precio</div>
                                                            <div class="menu-product-price">${{ number_format($product->price, 2) }}</div>
                                                        </div>

                                                        <div class="table-actions">
                                                            @if($canEditProduct)
                                                                <a href="{{ route('products.menu.edit', $product) }}" class="btn btn-outline-primary btn-sm">
                                                                    Editar
                                                                </a>
                                                            @endif

                                                            @if($canDeleteProduct)
                                                                <form method="POST" action="{{ route('products.menu.destroy', $product) }}" onsubmit="return confirm('Deseas eliminar este producto?');">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="btn btn-outline-danger btn-sm">Eliminar</button>
                                                                </form>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            </article>
                                        @endforeach
                                    </div>
                                </div>
                            </section>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="col-xl-4">
                <div class="card module-card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-layer-group"></i> Categorias</h5>
                    </div>
                    <div class="card-body">
                        @if($canCreateCategory)
                            <form method="POST" action="{{ route('products.categories.store') }}" class="category-admin-form">
                                @csrf
                                <div class="category-admin-card">
                                    <h6 class="mb-3">Nueva categoria</h6>
                                    <div class="mb-3">
                                        <label class="form-label" for="category_create_name">Nombre</label>
                                        <input type="text" class="form-control" id="category_create_name" name="name" value="{{ old('name') }}" required>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-sm-6">
                                            <label class="form-label" for="category_create_sort_order">Orden</label>
                                            <input type="number" class="form-control" id="category_create_sort_order" name="sort_order" min="0" value="{{ old('sort_order', $categories->max('sort_order') + 1) }}" required>
                                        </div>
                                        <div class="col-sm-6">
                                            <label class="form-label d-block">Estado</label>
                                            <div class="form-check form-switch mt-2">
                                                <input class="form-check-input" type="checkbox" id="category_create_is_active" name="is_active" value="1" @checked(old('is_active', true))>
                                                <label class="form-check-label" for="category_create_is_active">Visible para meseros</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <label class="form-label" for="category_create_description">Descripcion</label>
                                        <textarea class="form-control" id="category_create_description" name="description" rows="3">{{ old('description') }}</textarea>
                                    </div>
                                    <div class="form-help">Si desactivas una categoria, sus productos dejan de mostrarse en la toma de pedidos.</div>
                                    <div class="form-actions">
                                        <button type="submit" class="btn btn-primary">Crear categoria</button>
                                    </div>
                                </div>
                            </form>
                        @endif

                        <div class="category-admin-list">
                            @forelse($categories as $category)
                                <details class="category-admin-card" {{ !$category->is_active ? 'open' : '' }}>
                                    <summary class="category-admin-summary">
                                        <div>
                                            <strong>{{ $category->name }}</strong>
                                            <div class="table-note">Orden {{ $category->sort_order }} | {{ $category->simple_products_count }} productos simples</div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge rounded-pill {{ $category->is_active ? 'bg-success' : 'bg-secondary' }}">
                                                {{ $category->is_active ? 'Activa' : 'Oculta' }}
                                            </span>
                                            <i class="fas fa-chevron-down"></i>
                                        </div>
                                    </summary>

                                    <div class="category-admin-content">
                                        <p class="table-note mb-3">{{ $category->description ?: 'Sin descripcion registrada para esta categoria.' }}</p>

                                        @if($canEditCategory)
                                            <form method="POST" action="{{ route('products.categories.update', $category) }}">
                                                @csrf
                                                @method('PUT')
                                                <div class="mb-3">
                                                    <label class="form-label" for="category_name_{{ $category->id }}">Nombre</label>
                                                    <input type="text" class="form-control" id="category_name_{{ $category->id }}" name="name" value="{{ old('name', $category->name) }}" required>
                                                </div>
                                                <div class="row g-3">
                                                    <div class="col-sm-6">
                                                        <label class="form-label" for="category_sort_order_{{ $category->id }}">Orden</label>
                                                        <input type="number" class="form-control" id="category_sort_order_{{ $category->id }}" name="sort_order" min="0" value="{{ old('sort_order', $category->sort_order) }}" required>
                                                    </div>
                                                    <div class="col-sm-6">
                                                        <label class="form-label d-block">Estado</label>
                                                        <div class="form-check form-switch mt-2">
                                                            <input class="form-check-input" type="checkbox" id="category_is_active_{{ $category->id }}" name="is_active" value="1" @checked(old('is_active', $category->is_active))>
                                                            <label class="form-check-label" for="category_is_active_{{ $category->id }}">Visible para meseros</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="mt-3">
                                                    <label class="form-label" for="category_description_{{ $category->id }}">Descripcion</label>
                                                    <textarea class="form-control" id="category_description_{{ $category->id }}" name="description" rows="3">{{ old('description', $category->description) }}</textarea>
                                                </div>
                                                <div class="form-actions">
                                                    <button type="submit" class="btn btn-outline-primary">Guardar cambios</button>
                                                </div>
                                            </form>
                                        @endif

                                        @if($canDeleteCategory)
                                            <form method="POST" action="{{ route('products.categories.destroy', $category) }}" onsubmit="return confirm('Deseas eliminar esta categoria?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger w-100" @disabled($category->simple_products_count > 0)>
                                                    {{ $category->simple_products_count > 0 ? 'Elimina o mueve sus productos primero' : 'Eliminar categoria' }}
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </details>
                            @empty
                                <p class="text-muted mb-0">Aun no hay categorias definidas.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
