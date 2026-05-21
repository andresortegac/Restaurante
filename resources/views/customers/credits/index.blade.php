@extends('layouts.app')

@section('title', 'Creditos de clientes - RestaurantePOS')

@section('content')
    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Clientes / Cartera</span>
                <h1>Gestion de creditos</h1>
                <p>Consulta la cartera pendiente y el saldo a favor de cada cliente para entrar a su gestion completa.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">{{ $summary['customersWithCredit'] }} clientes con saldo</span>
                <span class="summary-chip">{{ $summary['customersWithAvailableBalance'] }} con saldo a favor</span>
                <span class="summary-chip">{{ $summary['pendingCredits'] }} cuentas pendientes</span>
                <span class="summary-chip">${{ money($summary['creditPending']) }} por cobrar</span>
                <span class="summary-chip">${{ money($summary['availableBalance']) }} a favor</span>
            </div>
        </section>

        <div class="card module-card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('customers.credits.index') }}">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="search">Buscar cliente</label>
                            <input type="text" class="form-control" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nombre, documento, telefono o email">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="balance">Mostrar</label>
                            <select class="form-select" id="balance" name="balance">
                                <option value="activity" @selected(($filters['balance'] ?? 'activity') === 'activity')>Con cartera o saldo a favor</option>
                                <option value="pending" @selected(($filters['balance'] ?? '') === 'pending')>Solo con saldo pendiente</option>
                                <option value="favor" @selected(($filters['balance'] ?? '') === 'favor')>Solo con saldo a favor</option>
                                <option value="all" @selected(($filters['balance'] ?? '') === 'all')>Todos los clientes</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="{{ route('customers.credits.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                        <button type="submit" class="btn btn-primary">Filtrar cartera</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card module-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Contacto</th>
                                <th>Cuentas pendientes</th>
                                <th>Cartera</th>
                                <th>Saldo a favor</th>
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
                                    <td>{{ number_format((int) ($customer->pending_credits_count ?? 0)) }}</td>
                                    <td>
                                        <strong>${{ money($customer->pending_credit_total ?? 0) }}</strong>
                                        <div class="table-note">
                                            @if((float) ($customer->pending_credit_total ?? 0) > 0)
                                                Cartera pendiente
                                            @else
                                                Sin saldo
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <strong>${{ money($customer->available_balance ?? 0) }}</strong>
                                        <div class="table-note">
                                            @if((float) ($customer->available_balance ?? 0) > 0)
                                                Disponible para futuros cobros
                                            @else
                                                Sin saldo a favor
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div class="table-actions justify-content-end">
                                            <a href="{{ route('customers.credits.show', $customer) }}" class="btn btn-primary btn-sm">Gestionar</a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No encontramos clientes con esos filtros.</td>
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
