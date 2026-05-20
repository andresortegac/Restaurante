@extends('layouts.app')

@section('title', 'Cartera de ' . $customer->name . ' - RestaurantePOS')

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
                <span class="module-kicker">Clientes / Cartera</span>
                <h1>{{ $customer->name }}</h1>
                <p>Administra los saldos pendientes del cliente y registra nuevos cargos sin afectar caja hasta el recaudo.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">${{ number_format($summary['pending'], 2) }} pendiente</span>
                <span class="summary-chip">{{ $summary['pendingCount'] }} cuentas activas</span>
                <span class="summary-chip">{{ $summary['paidCount'] }} pagadas</span>
            </div>
        </section>

        @include('products.partials.form-errors')

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card module-card service-card">
                    <div class="card-header d-flex justify-content-between align-items-center gap-3">
                        <div>
                            <h5 class="mb-1">Asignar saldo pendiente</h5>
                            <p class="table-note mb-0">Usa esta opcion para registrar una deuda manual del cliente.</p>
                        </div>
                        <a href="{{ route('customers.credits.index') }}" class="btn btn-outline-secondary btn-sm">Volver</a>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('customers.credits.store', $customer) }}">
                            @csrf

                            <div class="mb-3">
                                <label class="form-label" for="description">Concepto</label>
                                <input type="text" class="form-control" id="description" name="description" value="{{ old('description') }}" placeholder="Ej: saldo anterior, acuerdo de pago" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="amount">Valor pendiente</label>
                                <input type="number" class="form-control" id="amount" name="amount" min="0.01" step="0.01" value="{{ old('amount') }}" required>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Guardar saldo pendiente</button>
                        </form>
                    </div>
                </div>

                <div class="card module-card service-card mt-4">
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

            <div class="col-lg-8">
                <div class="card module-card service-card">
                    <div class="card-header">
                        <h5 class="mb-0">Cuentas del cliente</h5>
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
                                            <th class="text-end">Acciones</th>
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
                                                    <strong>${{ number_format((float) $credit->amount, 2) }}</strong>
                                                    @if((float) $credit->amount > (float) $credit->balance)
                                                        <div class="table-note">Abonado: ${{ number_format((float) $credit->amount - (float) $credit->balance, 2) }}</div>
                                                    @endif
                                                </td>
                                                <td>
                                                    <strong>${{ number_format((float) $credit->balance, 2) }}</strong>
                                                    @if($credit->paid_at)
                                                        <div class="table-note">Pagado {{ $credit->paid_at->format('d/m/Y H:i') }}</div>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="table-actions justify-content-end">
                                                        @if($credit->status === 'pending' && $credit->sale_id)
                                                            <form method="POST" action="{{ route('billing.credits.pay', $credit->sale) }}" class="d-flex align-items-center gap-2 m-0" data-credit-payment-form data-credit-label="{{ $credit->description ?: 'Saldo pendiente' }}">
                                                                @csrf
                                                                <input type="number" name="amount_received" class="form-control form-control-sm" min="0.01" max="{{ number_format((float) $credit->balance, 2, '.', '') }}" step="0.01" value="{{ number_format((float) $credit->balance, 2, '.', '') }}" style="width: 120px;">
                                                                <input type="hidden" name="redirect_back" value="1">
                                                                <button type="submit" class="btn btn-success btn-sm">Cobrar</button>
                                                            </form>
                                                        @elseif($credit->status === 'pending')
                                                            <form method="POST" action="{{ route('customers.credits.pay', [$customer, $credit]) }}" class="d-flex align-items-center gap-2 m-0" data-credit-payment-form data-credit-label="{{ $credit->description ?: 'Saldo pendiente' }}">
                                                                @csrf
                                                                <input type="number" name="amount_received" class="form-control form-control-sm" min="0.01" max="{{ number_format((float) $credit->balance, 2, '.', '') }}" step="0.01" value="{{ number_format((float) $credit->balance, 2, '.', '') }}" style="width: 120px;">
                                                                <button type="submit" class="btn btn-success btn-sm">Cobrar</button>
                                                            </form>
                                                        @else
                                                            <span class="text-muted small">Sin acciones</span>
                                                        @endif
                                                    </div>
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
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-credit-payment-form]').forEach(function (form) {
            form.addEventListener('submit', async function (event) {
                event.preventDefault();

                const amountInput = form.querySelector('input[name="amount_received"]');
                const amount = Number(amountInput?.value || 0);
                const label = form.dataset.creditLabel || 'este saldo';

                if (!amount || amount <= 0) {
                    if (window.Swal) {
                        await Swal.fire({
                            icon: 'warning',
                            title: 'Falta el abono',
                            text: 'Ingresa un valor valido para registrar el cobro.',
                            confirmButtonText: 'Aceptar',
                            confirmButtonColor: '#2563eb',
                        });
                    } else {
                        alert('Ingresa un valor valido para registrar el cobro.');
                    }

                    return;
                }

                if (window.Swal) {
                    const result = await Swal.fire({
                        icon: 'question',
                        title: 'Confirmar cobro',
                        text: 'Se registrara un abono de $' + amount.toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' para ' + label + '.',
                        showCancelButton: true,
                        confirmButtonText: 'Registrar',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#198754',
                        cancelButtonColor: '#6c757d',
                    });

                    if (!result.isConfirmed) {
                        return;
                    }
                }

                form.submit();
            });
        });
    });
    </script>
@endsection
