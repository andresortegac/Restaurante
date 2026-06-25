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
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-start gap-3">
                        <div>
                            <h5 class="mb-1">Saldo a favor</h5>
                            <div class="table-note">Disponible, consumido y movimientos del cliente</div>
                        </div>
                        <div class="d-flex flex-wrap align-items-center justify-content-start justify-content-lg-end gap-2">
                            @if($summary['pending'] > 0)
                                <a href="{{ route('customers.credits.collect', $customer) }}" class="btn btn-success btn-sm px-3">Cobrar deuda</a>
                            @endif
                            <a href="{{ route('customers.credits.payments.history', $customer) }}" class="btn btn-outline-primary btn-sm px-3">Historial de pagos</a>
                            <a href="{{ route('customers.credits.consumed-invoices', $customer) }}" class="btn btn-outline-success btn-sm px-3">Facturas</a>
                            <a href="{{ route('customers.credits.debt-summary.print', $customer) }}" class="btn btn-outline-dark btn-sm px-3" target="_blank" rel="noopener">Tirilla deuda</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <div class="table-note text-uppercase">Disponible</div>
                            <div class="display-6 fw-semibold mb-2">${{ money($summary['available']) }}</div>
                            <p class="mb-0">Este valor se puede descontar en cobros manuales o pedidos de mesa cuando selecciones al cliente.</p>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="table-note text-uppercase">Consumido</div>
                                    <div class="h5 mb-1">${{ money($summary['consumed']) }}</div>
                                    <div class="table-note">Por cobrar</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="table-note text-uppercase">Le queda</div>
                                    <div class="h5 mb-1">${{ money($summary['remainingToTop']) }}</div>
                                    <div class="table-note">Para consumir</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="table-note text-uppercase">Tope</div>
                                    <div class="h5 mb-1">${{ money($summary['top']) }}</div>
                                    <div class="table-note">Consumido + disponible</div>
                                </div>
                            </div>
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
