@extends('layouts.app')

@section('title', 'Historial de credito de ' . $customer->name . ' - RestaurantePOS')

@section('content')
    @php
        $sourceLabels = [
            'manual_assignment' => 'Saldo manual',
            'manual_charge' => 'Cobro manual',
            'table_order' => 'Cuenta por cobrar',
        ];
    @endphp

    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Clientes / Historial de credito</span>
                <h1>{{ $customer->name }}</h1>
                <p>Consulta los movimientos de cartera del cliente y revisa que creditos siguen pendientes o ya fueron pagados.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">${{ money($summary['pending']) }} pendiente</span>
                <span class="summary-chip">${{ money($summary['available']) }} saldo a favor</span>
                <span class="summary-chip">{{ $summary['pendingCount'] }} creditos pendientes</span>
                <span class="summary-chip">{{ $summary['paidCount'] }} creditos pagados</span>
            </div>
        </section>

        <div class="card module-card service-card">
            <div class="card-header d-flex justify-content-between align-items-center gap-3">
                <div>
                    <h5 class="mb-1">Historial del credito</h5>
                    <p class="table-note mb-0">Para registrar pagos usa el resumen de cobro del cliente.</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="{{ route('customers.credits.balance-history', $customer) }}" class="btn btn-outline-primary btn-sm">Ver saldo a favor</a>
                    <a href="{{ route('customers.credits.show', $customer) }}" class="btn btn-primary btn-sm">Volver a cobrar</a>
                    <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary btn-sm">Volver</a>
                </div>
            </div>
            <div class="card-body">
                @if($credits->isEmpty())
                    <div class="empty-state py-4">
                        <i class="fas fa-wallet"></i>
                        <h5 class="mb-2">Sin movimientos de cartera</h5>
                        <p class="mb-0">Cuando registres creditos manuales o envies ventas a credito, apareceran aqui.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Concepto</th>
                                    <th>Origen</th>
                                    <th>Estado</th>
                                    <th>Monto</th>
                                    <th>Saldo</th>
                                    <th>Referencia</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($credits as $credit)
                                    <tr>
                                        <td>
                                            <strong>{{ $credit->description ?: 'Saldo pendiente' }}</strong>
                                            <div class="table-note">{{ $credit->sale_id ? 'Venta #' . $credit->sale_id : 'Registro manual' }}</div>
                                        </td>
                                        <td>
                                            <strong>{{ $sourceLabels[$credit->source_type] ?? 'Cartera' }}</strong>
                                            <div class="table-note">
                                                @if($credit->sale?->tableOrder?->order_number)
                                                    {{ $credit->sale->tableOrder->order_number }}{{ $credit->sale->tableOrder->table?->name ? ' | ' . $credit->sale->tableOrder->table->name : '' }}
                                                @elseif($credit->createdBy?->name)
                                                    Registrado por {{ $credit->createdBy->name }}
                                                @else
                                                    Sin referencia adicional
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge rounded-pill {{ $credit->status === 'pending' ? 'bg-warning text-dark' : 'bg-success' }}">
                                                {{ $credit->status === 'pending' ? 'Pendiente' : 'Pagado' }}
                                            </span>
                                            <div class="table-note">
                                                {{ $credit->created_at?->format('d/m/Y H:i') }}
                                            </div>
                                        </td>
                                        <td>
                                            <strong>${{ money($credit->amount) }}</strong>
                                            @if((float) $credit->amount > (float) $credit->balance)
                                                <div class="table-note">Abonado: ${{ money((float) $credit->amount - (float) $credit->balance) }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <strong>${{ money($credit->balance) }}</strong>
                                            <div class="table-note">
                                                @if($credit->paid_at)
                                                    Pagado {{ $credit->paid_at->format('d/m/Y H:i') }}
                                                @elseif($credit->status === 'pending')
                                                    Cobrar desde el resumen del cliente
                                                @else
                                                    Sin novedad
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <div>{{ $credit->paid_reference ?: 'Sin referencia' }}</div>
                                            <div class="table-note">{{ $credit->paymentMethod?->name ?: 'Sin metodo registrado' }}</div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        {{ $credits->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
