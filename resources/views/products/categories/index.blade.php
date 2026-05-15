@extends('layouts.app')

@section('title', 'Categorias - ' . config('app.name', 'Solomo & Pomo'))

@section('content')
    @php
        $user = Auth::user();
        $canCreateCategory = $user->hasRole('Admin') || $user->hasPermission('products.create');
        $canEditCategory = $user->hasRole('Admin') || $user->hasPermission('products.edit');
        $canDeleteCategory = $user->hasRole('Admin') || $user->hasPermission('products.delete');
    @endphp

    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Gestion de Productos / Categorias</span>
                <h1>Categorias del menu</h1>
                <p>Administra las categorias en una vista propia para no quitar espacio al catalogo de productos. Desde aqui puedes crear, ordenar, ocultar y actualizar las secciones visibles para meseros.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">{{ $categories->count() }} categorias</span>
                <span class="summary-chip">{{ $categories->where('is_active', true)->count() }} activas</span>
                <span class="summary-chip">{{ $categories->where('is_active', false)->count() }} ocultas</span>
                <span class="summary-chip">{{ $simpleProductsCount }} productos simples</span>
            </div>
        </section>

        @include('products.partials.form-errors')

        <div class="module-toolbar">
            <div>
                <h5 class="mb-1">Panel de categorias</h5>
                <p class="table-note mb-0">Las categorias ordenan el menu grafico y controlan que grupos aparecen durante la toma de pedidos.</p>
            </div>
            <a href="{{ route('products.menu.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-book-open"></i> Volver al menu
            </a>
        </div>

        <div class="row g-4">
            @if($canCreateCategory)
                <div class="col-xl-4">
                    <div class="card module-card h-100">
                        <div class="card-body">
                            <form method="POST" action="{{ route('products.categories.store') }}" class="category-admin-form">
                                @csrf
                                <div class="category-admin-card category-admin-card--solid">
                                    <h6 class="mb-3">Nueva categoria</h6>
                                    <div class="mb-3">
                                        <label class="form-label" for="category_create_name">Nombre</label>
                                        <input type="text" class="form-control" id="category_create_name" name="name" value="{{ old('name') }}" required>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-sm-6">
                                            <label class="form-label" for="category_create_sort_order">Orden</label>
                                            <input type="number" class="form-control" id="category_create_sort_order" name="sort_order" min="1" value="{{ old('sort_order', max(1, $categories->max('sort_order') + 1)) }}" required>
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
                        </div>
                    </div>
                </div>
            @endif

            <div class="{{ $canCreateCategory ? 'col-xl-8' : 'col-12' }}">
                <div class="category-admin-list">
                    @forelse($categories as $category)
                        <details class="category-admin-card" {{ !$category->is_active ? 'open' : '' }}>
                            <summary class="category-admin-summary">
                                <div>
                                    <strong>{{ $category->name }}</strong>
                                    <div class="table-note">Orden {{ $category->sort_order }} | {{ $category->simple_products_count }} productos simples | {{ $category->active_products_count }} activos</div>
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
                                                <input type="number" class="form-control" id="category_sort_order_{{ $category->id }}" name="sort_order" min="1" value="{{ old('sort_order', max(1, $category->sort_order)) }}" required>
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
                                        <div class="form-actions category-admin-actions">
                                            <button type="submit" class="btn btn-outline-primary">Guardar cambios</button>
                                        </div>
                                    </form>
                                @endif

                                @if($canDeleteCategory)
                                    <form class="category-admin-delete-form" method="POST" action="{{ route('products.categories.destroy', $category) }}" onsubmit="return confirm('Deseas eliminar esta categoria?');">
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
                        <div class="card module-card">
                            <div class="card-body">
                                <p class="text-muted mb-0">Aun no hay categorias definidas.</p>
                            </div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
