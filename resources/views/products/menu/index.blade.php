@extends('layouts.app')

@section('title', 'Menu y Precios - RestaurantePOS')

@section('content')
    @php
        $canCreateProduct = Auth::user()->hasRole('Admin') || Auth::user()->hasPermission('products.create');
        $canEditProduct = Auth::user()->hasRole('Admin') || Auth::user()->hasPermission('products.edit');
        $canDeleteProduct = Auth::user()->hasRole('Admin') || Auth::user()->hasPermission('products.delete');
    @endphp

    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Gestion de Productos / RF-20</span>
                <h1>Menu, categorias y precios</h1>
                <p>Administra los productos cobrables del restaurante y crea nuevas categorias escribiendo su nombre directamente en el formulario del producto.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">{{ $products->count() }} productos</span>
                <span class="summary-chip">{{ $categories->count() }} categorias</span>
                <span class="summary-chip">{{ $products->where('active', true)->count() }} activos</span>
            </div>
        </section>

        <div class="module-toolbar">
            <div>
                <h5 class="mb-1">Catalogo del menu</h5>
                <p class="table-note mb-0">Desde aqui puedes crear, editar o desactivar los productos que se venden en caja.</p>
            </div>
            @if($canCreateProduct)
                <a href="{{ route('products.menu.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nuevo producto
                </a>
            @endif
        </div>

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="card module-card h-100">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Categoria</th>
                                        <th>Precio</th>
                                        <th>Impuesto</th>
                                        <th>Stock</th>
                                        <th>Estado</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($products as $product)
                                        <tr>
                                            <td>
                                                <strong>{{ $product->name }}</strong>
                                                <div class="table-note">{{ $product->sku }}</div>
                                            </td>
                                            <td>{{ $product->menuCategory->name ?? $product->category }}</td>
                                            <td>${{ number_format($product->price, 2) }}</td>
                                            <td>{{ $product->taxRate->name ?? 'Sin impuesto' }}</td>
                                            <td>{{ $product->stock }}</td>
                                            <td>
                                                <span class="badge rounded-pill {{ $product->active ? 'bg-success' : 'bg-secondary' }}">
                                                    {{ $product->active ? 'Activo' : 'Inactivo' }}
                                                </span>
                                            </td>
                                            <td>
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
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">Todavia no hay productos cargados en el menu.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card module-card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-layer-group"></i> Categorias</h5>
                    </div>
                    <div class="card-body">
                        <div class="module-list">
                            @forelse($categories as $category)
                                <div class="module-list-item">
                                    <div>
                                        <strong>{{ $category->name }}</strong>
                                        <div class="table-note">{{ $category->description ?: 'Categoria disponible para el menu.' }}</div>
                                    </div>
                                    <span class="summary-chip">{{ $category->simple_products_count }}</span>
                                </div>
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
