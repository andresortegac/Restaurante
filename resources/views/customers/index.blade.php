@extends('layouts.app')

@section('title', 'Clientes - RestaurantePOS')

@section('content')
    @php
        $canCreateCustomer = Auth::user()->hasRole('Admin') || Auth::user()->hasPermission('customers.create');
        $canEditCustomer = Auth::user()->hasRole('Admin') || Auth::user()->hasPermission('customers.edit');
        $canDeleteCustomer = Auth::user()->hasRole('Admin') || Auth::user()->hasPermission('customers.delete');
    @endphp

    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Clientes / CRM basico</span>
                <h1>Clientes del restaurante</h1>
                <p>Administra el directorio de clientes para seleccionarlos rapidamente al tomar pedidos en mesa.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">{{ $summary['total'] }} registrados</span>
                <span class="summary-chip">{{ $summary['active'] }} activos</span>
                <span class="summary-chip">{{ $summary['inactive'] }} inactivos</span>
            </div>
        </section>

        <div class="module-toolbar">
            <form method="GET" action="{{ route('customers.index') }}" class="row g-2 align-items-end flex-grow-1">
                <div class="col-md-6">
                    <label class="form-label" for="search">Buscar</label>
                    <input type="text" class="form-control" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nombre, documento, telefono o email">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="status">Estado</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>Activos</option>
                        <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactivos</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary w-100">Filtrar</button>
                    <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary w-100">Limpiar</a>
                </div>
            </form>

            @if($canCreateCustomer)
                <a href="{{ route('customers.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nuevo cliente
                </a>
            @endif
        </div>

        <div class="card module-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Contacto</th>
                                <th>Movimientos</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($customers as $customer)
                                <tr>
                                    <td>
                                        <strong>{{ $customer->name }}</strong>
                                        <div class="table-note">{{ $customer->document_number ?: 'Sin documento' }}</div>
                                    </td>
                                    <td>
                                        <div>{{ $customer->phone ?: 'Sin telefono' }}</div>
                                        <div class="table-note">{{ $customer->email ?: 'Sin email' }}</div>
                                    </td>
                                    <td>
                                        <div>{{ $customer->table_orders_count }} pedidos</div>
                                        <div class="table-note">{{ $customer->sales_count }} ventas</div>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill {{ $customer->is_active ? 'bg-success' : 'bg-secondary' }}">
                                            {{ $customer->is_active ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="table-actions justify-content-end">
                                            @if($canEditCustomer)
                                                <a href="{{ route('customers.edit', $customer) }}" class="btn btn-outline-primary btn-sm">Editar</a>
                                            @endif
                                            @if($canDeleteCustomer)
                                                <form method="POST" action="{{ route('customers.destroy', $customer) }}" onsubmit="return confirm('Deseas eliminar este cliente?');">
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
                                    <td colspan="5" class="text-center py-4 text-muted">Todavia no hay clientes registrados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3">
            {{ $customers->links() }}
        </div>
    </div>
@endsection
