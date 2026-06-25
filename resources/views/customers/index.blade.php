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
                <span class="module-kicker">Clientes</span>
                <h1>Clientes del restaurante</h1>
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
                                <th>Consumido</th>
                                <th>Saldo a favor</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($customers as $customer)
                                @php
                                    $balanceDebt = money_value(max(0, abs((float) ($customer->consumed_balance_total ?? 0)) - (float) ($customer->paid_balance_total ?? 0)));
                                    $pendingTotal = money_value((float) ($customer->pending_credit_total ?? 0) + $balanceDebt);
                                @endphp
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
                                        <strong>${{ money($balanceDebt) }}</strong>
                                        <div class="table-note">Por cobrar desde saldo a favor</div>
                                    </td>
                                    <td>
                                        <strong>${{ money($customer->available_balance ?? 0) }}</strong>
                                        <div class="table-note">
                                            @if($pendingTotal > 0)
                                                Debe ${{ money($pendingTotal) }}
                                            @elseif((float) ($customer->available_balance ?? 0) > 0)
                                                Disponible
                                            @else
                                                Sin saldo a favor
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill {{ $customer->is_active ? 'bg-success' : 'bg-secondary' }}">
                                            {{ $customer->is_active ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="table-actions justify-content-end">
                                            <a href="{{ route('customers.credits.show', $customer) }}" class="btn btn-outline-secondary btn-sm px-3">Saldo</a>
                                            @if($pendingTotal > 0)
                                                <a href="{{ route('customers.credits.collect', $customer) }}" class="btn btn-success btn-sm px-3">Cobrar deuda</a>
                                            @endif
                                            @if($canEditCustomer)
                                                <a href="{{ route('customers.edit', $customer) }}" class="btn btn-outline-primary btn-sm px-3">Editar</a>
                                            @endif
                                            @if($canDeleteCustomer)
                                                <form method="POST" action="{{ route('customers.destroy', $customer) }}" class="m-0 w-100 customer-delete-form" data-customer-name="{{ $customer->name }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-outline-danger btn-sm px-3 w-100">Eliminar</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">Todavia no hay clientes registrados.</td>
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

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.customer-delete-form').forEach(function (form) {
                form.addEventListener('submit', async function (event) {
                    event.preventDefault();

                    const customerName = form.dataset.customerName || 'este cliente';
                    const message = 'Deseas eliminar a ' + customerName + '?';

                    if (! window.Swal) {
                        if (confirm(message)) {
                            form.submit();
                        }

                        return;
                    }

                    const result = await Swal.fire({
                        icon: 'warning',
                        title: 'Eliminar cliente',
                        text: message,
                        showCancelButton: true,
                        confirmButtonText: 'Eliminar',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                    });

                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });
    </script>
@endpush
