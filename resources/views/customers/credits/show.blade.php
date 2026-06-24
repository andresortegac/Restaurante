@extends('layouts.app')

@section('title', 'Saldo a favor de ' . $customer->name . ' - RestaurantePOS')

@section('content')
    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Clientes / Saldo a favor</span>
                <h1>{{ $customer->name }}</h1>
            </div>
            <div class="summary-group">
                <span class="summary-chip">${{ money($summary['available']) }} saldo a favor</span>
            </div>
        </section>

        @include('products.partials.form-errors')

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card module-card service-card">
                    <div class="card-header d-flex justify-content-between align-items-center gap-3">
                        <div>
                            <h5 class="mb-1">Saldo a favor</h5>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <a href="{{ route('customers.credits.consumed-invoices', $customer) }}" class="btn btn-outline-success btn-sm">Facturas consumidas</a>
                            <a href="{{ route('customers.credits.debt-summary.print', $customer) }}" class="btn btn-outline-dark btn-sm" target="_blank" rel="noopener">Imprimir deuda</a>
                            <a href="{{ route('customers.credits.balance-history', $customer) }}" class="btn btn-outline-primary btn-sm">Ver historial del saldo a favor</a>
                            <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary btn-sm">Volver</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <div class="table-note text-uppercase">Disponible</div>
                            <div class="display-6 fw-semibold mb-2">${{ money($summary['available']) }}</div>
                            <p class="mb-0">Este valor se puede descontar en cobros manuales o pedidos de mesa cuando selecciones al cliente.</p>
                        </div>

                        <form method="POST" action="{{ route('customers.credits.balance.store', $customer) }}">
                            @csrf

                            <div class="mb-3">
                                <label class="form-label" for="operation">Movimiento</label>
                                <select class="form-select" id="operation" name="operation">
                                    <option value="add" @selected(old('operation', 'add') === 'add')>Agregar saldo a favor</option>
                                    <option value="remove" @selected(old('operation') === 'remove')>Quitar saldo a favor</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="balance_amount">Valor</label>
                                <input type="number" class="form-control" id="balance_amount" name="amount" min="1" step="1" value="{{ money_input(old('amount', 0)) }}" required>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Guardar saldo a favor</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card module-card service-card">
                    <div class="card-header">
                        <h5 class="mb-0">Datos del cliente</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-2"><strong>Documento:</strong> {{ $customer->document_number ?: 'Sin documento' }}</div>
                        <div class="mb-2"><strong>Telefono:</strong> {{ $customer->phone ?: 'Sin telefono' }}</div>
                        <div class="mb-2"><strong>Email:</strong> {{ $customer->email ?: 'Sin email' }}</div>
                        <div><strong>Notas:</strong> {{ $customer->notes ?: 'Sin notas' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
