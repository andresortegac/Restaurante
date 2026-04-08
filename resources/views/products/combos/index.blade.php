@extends('layouts.app')

@section('title', 'Combos - RestaurantePOS')

@section('content')
    @php
        $canCreateCombo = Auth::user()->hasRole('Admin') || Auth::user()->hasPermission('combos.create');
        $canEditCombo = Auth::user()->hasRole('Admin') || Auth::user()->hasPermission('combos.edit');
        $canDeleteCombo = Auth::user()->hasRole('Admin') || Auth::user()->hasPermission('combos.delete');
    @endphp

    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Gestion de Productos / RF-21</span>
                <h1>Combos y productos compuestos</h1>
                <p>Crea referencias comerciales que agrupan varios productos simples bajo un solo precio de venta.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">{{ $combos->count() }} combos</span>
                <span class="summary-chip">{{ $simpleProductsCount }} productos simples</span>
                <span class="summary-chip">{{ $combos->where('active', true)->count() }} activos</span>
            </div>
        </section>

        <div class="module-toolbar">
            <div>
                <h5 class="mb-1">Configuracion de combos</h5>
                <p class="table-note mb-0">Cada combo mantiene su propio SKU, precio e impuesto, pero ademas guarda el detalle de sus componentes.</p>
            </div>
            @if($canCreateCombo)
                <a href="{{ route('products.combos.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nuevo combo
                </a>
            @endif
        </div>

        <div class="row g-4">
            <div class="col-xl-9">
                <div class="card module-card h-100">
                    <div class="card-body">
                        @forelse($combos as $combo)
                            <article class="combo-card">
                                <div class="combo-card-header">
                                    <div>
                                        <h6 class="mb-1">{{ $combo->name }}</h6>
                                        <div class="table-note">{{ $combo->sku }} | {{ $combo->menuCategory->name ?? 'Sin categoria' }} | {{ $combo->taxRate->name ?? 'Sin impuesto' }} | {{ $combo->tracks_stock ? 'Controla stock' : 'Sin control de stock' }}</div>
                                    </div>
                                    <div class="combo-actions">
                                        <span class="summary-chip">${{ number_format($combo->price, 2) }}</span>
                                        @if($canEditCombo)
                                            <a href="{{ route('products.combos.edit', $combo) }}" class="btn btn-outline-primary btn-sm">Editar</a>
                                        @endif
                                        @if($canDeleteCombo)
                                            <form method="POST" action="{{ route('products.combos.destroy', $combo) }}" onsubmit="return confirm('Deseas eliminar este combo?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm">Eliminar</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>

                                <div class="combo-components">
                                    @forelse($combo->components as $component)
                                        <span class="component-pill">
                                            {{ rtrim(rtrim(number_format($component->quantity, 2), '0'), '.') }}
                                            {{ $component->unit_label ?: 'unidad' }}
                                            de {{ $component->componentProduct->name ?? 'Producto eliminado' }}
                                        </span>
                                    @empty
                                        <p class="text-muted mb-0">Este combo aun no tiene componentes asociados.</p>
                                    @endforelse
                                </div>
                            </article>
                        @empty
                            <div class="empty-state">
                                <i class="fas fa-layer-group"></i>
                                <h5>No hay combos registrados todavia</h5>
                                <p>Crea el primero para vender un paquete de productos desde el POS con un solo SKU.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-xl-3">
                <div class="card module-card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-chart-pie"></i> Resumen</h5>
                    </div>
                    <div class="card-body">
                        <div class="module-list">
                            <div class="module-list-item">
                                <div>
                                    <strong>Combos activos</strong>
                                    <div class="table-note">Disponibles para vender</div>
                                </div>
                                <span class="summary-chip">{{ $combos->where('active', true)->count() }}</span>
                            </div>

                            <div class="module-list-item">
                                <div>
                                    <strong>Combos inactivos</strong>
                                    <div class="table-note">Ocultos temporalmente del POS</div>
                                </div>
                                <span class="summary-chip">{{ $combos->where('active', false)->count() }}</span>
                            </div>

                            <div class="module-list-item">
                                <div>
                                    <strong>Base disponible</strong>
                                    <div class="table-note">Productos simples para componer combos</div>
                                </div>
                                <span class="summary-chip">{{ $simpleProductsCount }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
