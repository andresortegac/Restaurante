@extends('layouts.app')

@section('title', 'Menu y Precios - ' . config('app.name', 'Solomo & Pomo'))

@section('content')
    @php
        $user = Auth::user();
        $canCreateProduct = $user->hasRole('Admin') || $user->hasPermission('products.create');
        $canEditProduct = $user->hasRole('Admin') || $user->hasPermission('products.edit');
        $canDeleteProduct = $user->hasRole('Admin') || $user->hasPermission('products.delete');
        $canViewCategories = $user->hasRole('Admin') || $user->hasAnyPermission(['products.view', 'products.create', 'products.edit', 'products.delete']);

        $menuSections = $categories
            ->map(function ($category) use ($products) {
                return [
                    'key' => 'category-' . $category->id,
                    'title' => $category->name,
                    'description' => $category->description ?: 'Categoría disponible para el menú gráfico.',
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
                'description' => 'Productos pendientes por clasificar dentro del menú.',
                'is_active' => true,
                'products' => $uncategorizedProducts,
            ]);
        }
    @endphp

    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Gestion de Productos</span>
                <h1>Menu grafico y orden visual</h1>
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
            </div>
            <div class="menu-toolbar-actions">
                @if($canViewCategories)
                    <a href="{{ route('products.categories.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-tags"></i> Categorias
                    </a>
                @endif
                @if($canCreateProduct)
                    <a href="{{ route('products.menu.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Nuevo producto
                    </a>
                @endif
            </div>
        </div>

        @if($categories->isNotEmpty() || $uncategorizedProductsCount > 0)
            <div class="category-mini-strip">
                <div class="category-mini-strip__label">Categorias</div>
                <div class="category-mini-strip__items">
                    @foreach($categories as $category)
                        @if($category->simple_products_count > 0)
                            <a href="#category-{{ $category->id }}" class="category-mini-chip {{ $category->is_active ? '' : 'is-muted' }}">
                                <span>{{ $category->name }}</span>
                                <small>{{ $category->simple_products_count }}</small>
                            </a>
                        @else
                            <span class="category-mini-chip is-muted">
                                <span>{{ $category->name }}</span>
                                <small>0</small>
                            </span>
                        @endif
                    @endforeach

                    @if($uncategorizedProductsCount > 0)
                        <a href="#uncategorized" class="category-mini-chip is-muted">
                            <span>Sin categoria</span>
                            <small>{{ $uncategorizedProductsCount }}</small>
                        </a>
                    @endif
                </div>
            </div>
        @endif

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
                    <section class="card module-card menu-showcase-card" id="{{ $section['key'] }}">
                        <div class="card-body">
                            <div class="menu-showcase-header">
                                <div>
                                    <div class="summary-kicker">Categoria</div>
                                    <h5 class="mb-1">{{ $section['title'] }}</h5>
                                    <p class="table-note mb-0">{{ $section['description'] }}</p>
                                </div>
                                <div class="menu-showcase-pills">
                                    <span class="summary-chip">{{ $section['products']->count() }} items</span>
                                    @if(! $section['is_active'])
                                        <span class="summary-chip menu-chip-muted">Oculta para meseros</span>
                                    @endif
                                </div>
                            </div>

                            <div class="menu-product-grid">
                                @foreach($section['products'] as $product)
                                    <article class="menu-product-card {{ $product->active ? '' : 'is-inactive' }}">
                                        <div class="menu-product-media">
                                            @if($product->image_url)
                                                <img src="{{ $product->image_url }}" alt="{{ $product->name }}" loading="lazy" decoding="async">
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
                                                </div>
                                                <span class="badge rounded-pill {{ $product->active ? 'bg-success' : 'bg-secondary' }}">
                                                    {{ $product->active ? 'Activo' : 'Inactivo' }}
                                                </span>
                                            </div>

                                            <p class="menu-product-description">{{ $product->description ?: 'Sin descripcion registrada para este producto.' }}</p>

                                            <div class="menu-product-footer">
                                                <div>
                                                    <div class="summary-kicker">Precio</div>
                                                    <div class="menu-product-price">${{ money($product->price) }}</div>
                                                </div>

                                                <div class="table-actions menu-product-actions">
                                                    @if($canEditProduct)
                                                        <a href="{{ route('products.menu.edit', $product) }}" class="btn btn-outline-primary btn-sm">
                                                            Editar
                                                        </a>
                                                    @endif

                                                    @if($canDeleteProduct)
                                                        <form class="menu-product-action-form" method="POST" action="{{ route('products.menu.destroy', $product) }}" onsubmit="return confirm('Deseas eliminar este producto?');">
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
@endsection
