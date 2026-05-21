@extends('layouts.app')

@section('title', 'Historial de saldo a favor de ' . $customer->name . ' - RestaurantePOS')

@section('content')
    @php
        $movementLabels = [
            'manual_addition' => 'Ingreso manual',
            'manual_removal' => 'Descuento manual',
            'sale_consumption' => 'Consumo en venta',
        ];
    @endphp

    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Clientes / Historial de saldo a favor</span>
                <h1>{{ $customer->name }}</h1>
                <p>Consulta solo los movimientos del saldo a favor del cliente, sin mezclarlo con el dinero de caja.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">${{ number_format($summary['available'], 2) }} disponible</span>
                <span class="summary-chip">${{ number_format($summary['pending'], 2) }} en cartera</span>
                <span class="summary-chip">{{ $summary['pendingCount'] }} creditos pendientes</span>
            </div>
        </section>

        <div class="card module-card service-card">
            <div class="card-header d-flex justify-content-between align-items-center gap-3">
                <div>
                    <h5 class="mb-1">Historial del saldo a favor</h5>
                    <p class="table-note mb-0">Aqui puedes revisar entradas manuales, descuentos manuales y consumos aplicados en ventas.</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="{{ route('customers.credits.show', $customer) }}" class="btn btn-primary btn-sm">Volver a gestionar</a>
                    <a href="{{ route('customers.credits.index') }}" class="btn btn-outline-secondary btn-sm">Volver</a>
                </div>
            </div>
            <div class="card-body">
                @if($movements->isEmpty())
                    <div class="empty-state py-4">
                        <i class="fas fa-wallet"></i>
                        <h5 class="mb-2">Sin movimientos de saldo a favor</h5>
                        <p class="mb-0">Cuando agregues, quites o consumas saldo a favor, los movimientos apareceran aqui.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Movimiento</th>
                                    <th>Concepto</th>
                                    <th>Monto</th>
                                    <th>Saldo resultante</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($movements as $movement)
                                    @php
                                        $amount = (float) $movement->amount;
                                        $isPositive = $amount >= 0;
                                    @endphp
                                    <tr>
                                        <td>
                                            <strong>{{ $movement->created_at?->format('d/m/Y') }}</strong>
                                            <div class="table-note">{{ $movement->created_at?->format('H:i') }}</div>
                                        </td>
                                        <td>
                                            <strong>{{ $movementLabels[$movement->movement_type] ?? 'Saldo a favor' }}</strong>
                                            <div class="table-note">
                                                @if($movement->sale?->tableOrder?->order_number)
                                                    Venta #{{ $movement->sale_id }} | {{ $movement->sale->tableOrder->order_number }}
                                                @elseif($movement->sale_id)
                                                    Venta #{{ $movement->sale_id }}
                                                @elseif($movement->createdBy?->name)
                                                    Registrado por {{ $movement->createdBy->name }}
                                                @else
                                                    Registro manual
                                                @endif
                                            </div>
                                        </td>
                                        <td>{{ $movement->description }}</td>
                                        <td class="{{ $isPositive ? 'text-success' : 'text-danger' }}">
                                            <strong>{{ $isPositive ? '+' : '-' }}${{ number_format(abs($amount), 2) }}</strong>
                                            <div class="table-note">Antes: ${{ number_format((float) $movement->balance_before, 2) }}</div>
                                        </td>
                                        <td>
                                            <strong>${{ number_format((float) $movement->balance_after, 2) }}</strong>
                                            <div class="table-note">Disponible despues del movimiento</div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        {{ $movements->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
