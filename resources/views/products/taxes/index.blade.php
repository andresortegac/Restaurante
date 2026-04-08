@extends('layouts.app')

@section('title', 'Impuestos - RestaurantePOS')

@section('content')
    @php
        $canCreateTax = Auth::user()->hasRole('Admin') || Auth::user()->hasPermission('taxes.create');
        $canEditTax = Auth::user()->hasRole('Admin') || Auth::user()->hasPermission('taxes.edit');
        $canDeleteTax = Auth::user()->hasRole('Admin') || Auth::user()->hasPermission('taxes.delete');
    @endphp

    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Gestion de Productos / RF-22</span>
                <h1>Configuracion de impuestos</h1>
                <p>Define las reglas fiscales que luego podras asignar a productos y combos.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">{{ $taxRates->count() }} tasas</span>
                <span class="summary-chip">{{ $taxableProducts }} productos con impuesto</span>
                <span class="summary-chip">{{ $productsWithoutTax }} sin configurar</span>
            </div>
        </section>

        <div class="module-toolbar">
            <div>
                <h5 class="mb-1">Tasas registradas</h5>
                <p class="table-note mb-0">Puedes crear tasas nuevas, marcar una como predeterminada o desactivar reglas que ya no se usen.</p>
            </div>
            @if($canCreateTax)
                <a href="{{ route('products.taxes.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nuevo impuesto
                </a>
            @endif
        </div>

        <div class="card module-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Impuesto</th>
                                <th>Tasa</th>
                                <th>Modo</th>
                                <th>Productos</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($taxRates as $taxRate)
                                <tr>
                                    <td>
                                        <strong>{{ $taxRate->name }}</strong>
                                        <div class="table-note">{{ $taxRate->code ?: 'Sin codigo' }}</div>
                                    </td>
                                    <td>{{ number_format($taxRate->rate, 2) }}%</td>
                                    <td>{{ $taxRate->is_inclusive ? 'Incluido en precio' : 'Se suma al final' }}</td>
                                    <td>{{ $taxRate->products_count }}</td>
                                    <td>
                                        @if($taxRate->is_default)
                                            <span class="badge rounded-pill bg-primary">Predeterminado</span>
                                        @elseif($taxRate->is_active)
                                            <span class="badge rounded-pill bg-success">Activo</span>
                                        @else
                                            <span class="badge rounded-pill bg-secondary">Inactivo</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            @if($canEditTax)
                                                <a href="{{ route('products.taxes.edit', $taxRate) }}" class="btn btn-outline-primary btn-sm">Editar</a>
                                            @endif

                                            @if($canDeleteTax)
                                                <form method="POST" action="{{ route('products.taxes.destroy', $taxRate) }}" onsubmit="return confirm('Deseas eliminar este impuesto?');">
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
                                    <td colspan="6" class="text-center py-4 text-muted">No hay tasas de impuesto configuradas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
