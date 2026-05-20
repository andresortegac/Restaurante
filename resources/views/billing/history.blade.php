@extends('layouts.app')

@section('title', 'Ventas Generales - Facturacion')

@section('content')
    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Caja / Facturacion</span>
                <h1>Ventas generales</h1>
                <p>Consulta las ventas registradas del sistema, revisa si salieron como ticket o factura electronica y vuelve a imprimir el documento cuando haga falta.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">{{ number_format($summary['sales']) }} ventas</span>
                <span class="summary-chip">{{ number_format($summary['today']) }} hoy</span>
                <span class="summary-chip">${{ number_format($summary['revenue'], 2) }} vendidos</span>
                <span class="summary-chip">{{ number_format($summary['electronic']) }} electronicas</span>
                <span class="summary-chip">${{ number_format($summary['credit'], 2) }} en credito</span>
            </div>
        </section>

        <div class="card module-card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('billing.history') }}">
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <label class="form-label" for="search">Buscar</label>
                            <input type="text" class="form-control" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Venta, mesa, pedido, cliente, documento o CUFE">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="document_type">Documento</label>
                            <select class="form-select" id="document_type" name="document_type">
                                <option value="">Todos</option>
                                <option value="ticket" @selected(($filters['document_type'] ?? '') === 'ticket')>Ticket</option>
                                <option value="electronic" @selected(($filters['document_type'] ?? '') === 'electronic')>Factura electronica</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="payment_status">Pago</label>
                            <select class="form-select" id="payment_status" name="payment_status">
                                <option value="">Todos</option>
                                <option value="paid" @selected(($filters['payment_status'] ?? '') === 'paid')>Pagado</option>
                                <option value="credit" @selected(($filters['payment_status'] ?? '') === 'credit')>Credito</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="status">Estado</label>
                            <input type="text" class="form-control" id="status" name="status" value="{{ $filters['status'] ?? '' }}" placeholder="issued, validated, failed...">
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="{{ route('billing.history') }}" class="btn btn-outline-secondary">Limpiar</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filtrar ventas
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card module-card">
            <div class="card-body">
                @if($sales->isEmpty())
                    <div class="empty-state">
                        <i class="fas fa-clock-rotate-left"></i>
                        <h3>No hay ventas registradas</h3>
                        <p>Cuando registres ventas o cobros en el sistema, apareceran aqui.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Venta</th>
                                    <th>Fecha</th>
                                    <th>Origen</th>
                                    <th>Cajero</th>
                                    <th>Documento</th>
                                    <th>Cobro</th>
                                    <th>Total</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($sales as $sale)
                                    @php
                                        $primaryPayment = $sale->payments->first();
                                        $invoice = $sale->invoice;
                                        $remainingCreditBalance = (float) ($sale->customerCredit?->balance ?? $sale->total);
                                    @endphp
                                    <tr>
                                        <td>
                                            <strong>#{{ $sale->id }}</strong>
                                            <div class="text-muted small">{{ $sale->box?->name ?? 'Sin caja' }}</div>
                                        </td>
                                        <td>{{ $sale->created_at?->format('d/m/Y H:i') }}</td>
                                        <td>
                                            @if($sale->tableOrder)
                                                <strong>{{ $sale->tableOrder->order_number }}</strong>
                                                <div class="text-muted small">{{ $sale->tableOrder->table?->name ?? 'Mesa sin referencia' }}</div>
                                                <div class="text-muted small">{{ $sale->customer?->name ?: $sale->customer_name ?: 'Consumidor final' }}</div>
                                            @elseif($sale->delivery)
                                                <strong>{{ $sale->delivery->delivery_number }}</strong>
                                                <div class="text-muted small">{{ $sale->delivery->delivery_address }}</div>
                                                <div class="text-muted small">{{ $sale->customer?->name ?: $sale->customer_name ?: 'Consumidor final' }}</div>
                                            @elseif(str_contains((string) $sale->notes, 'manual'))
                                                <strong>{{ str_contains((string) $sale->notes, 'Domicilio manual') ? 'Domicilio manual' : 'Mesa manual' }}</strong>
                                                <div class="text-muted small">{{ $sale->customer?->name ?: $sale->customer_name ?: 'Consumidor final' }}</div>
                                                <div class="text-muted small">{{ $sale->notes }}</div>
                                            @else
                                                <strong>POS directo</strong>
                                                <div class="text-muted small">{{ $sale->customer?->name ?: $sale->customer_name ?: 'Consumidor final' }}</div>
                                            @endif
                                        </td>
                                        <td>{{ $sale->user?->name ?? 'Sin usuario' }}</td>
                                        <td>
                                            @if($invoice)
                                                <strong>{{ $invoice->isElectronic() ? 'Factura electronica' : 'Ticket' }}</strong>
                                                <div class="text-muted small">{{ $invoice->invoice_number }}</div>
                                                <div class="text-muted small">Estado: {{ $invoice->status }}</div>
                                                @if($invoice->cufe)
                                                    <div class="text-muted small">CUFE: {{ $invoice->cufe }}</div>
                                                @endif
                                            @else
                                                <span class="text-muted">Sin documento</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($primaryPayment)
                                                @if($sale->payment_status === 'credit')
                                                    <strong class="text-warning">Credito pendiente</strong>
                                                    <div class="text-muted small">Cliente: {{ $sale->customer?->name ?: $sale->customer_name ?: 'Sin cliente' }}</div>
                                                    <div class="text-muted small">Saldo pendiente: ${{ number_format($remainingCreditBalance, 2) }}</div>
                                                    <div class="text-muted small">Abonado: ${{ number_format(max(0, (float) $sale->total - $remainingCreditBalance), 2) }}</div>
                                                @else
                                                    <strong>{{ $sale->payments->map(fn ($payment) => $payment->paymentMethod?->name ?? 'Sin dato')->filter()->unique()->join(', ') ?: 'Sin pago' }}</strong>
                                                    <div class="text-muted small">Recibido: ${{ number_format((float) $primaryPayment->received_amount, 2) }}</div>
                                                    <div class="text-muted small">Cambio: ${{ number_format((float) $primaryPayment->change_amount, 2) }}</div>
                                                    <div class="text-muted small">Propina: ${{ number_format((float) $primaryPayment->tip_amount, 2) }}</div>
                                                @endif
                                            @else
                                                <span class="text-muted">Sin pago</span>
                                            @endif
                                        </td>
                                        <td>
                                            <strong>${{ number_format((float) $sale->total, 2) }}</strong>
                                            @if($primaryPayment && (float) $primaryPayment->tip_amount > 0)
                                                <div class="text-muted small">Con propina: ${{ number_format((float) $sale->total + (float) $primaryPayment->tip_amount, 2) }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="table-actions">
                                                <a href="{{ route('pos.sales.print', $sale) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-print"></i> Imprimir
                                                </a>
                                                @if($sale->payment_status === 'credit')
                                                    <form method="POST" action="{{ route('billing.credits.pay', $sale) }}" class="d-flex align-items-center gap-2 m-0" data-credit-payment-form data-credit-label="credito #{{ $sale->id }}">
                                                        @csrf
                                                        <input type="number" name="amount_received" class="form-control form-control-sm" min="0.01" max="{{ number_format($remainingCreditBalance, 2, '.', '') }}" step="0.01" value="{{ number_format($remainingCreditBalance, 2, '.', '') }}" style="width: 120px;">
                                                        <button type="submit" class="btn btn-sm btn-success">
                                                            <i class="fas fa-check"></i> Pagar credito
                                                        </button>
                                                    </form>
                                                @endif
                                                @if($invoice && $invoice->isElectronic())
                                                    <a href="{{ route('electronic-invoices.show', $invoice) }}" class="btn btn-sm btn-outline-secondary">
                                                        Ver FE
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        {{ $sales->links() }}
                    </div>
                @endif
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
                const label = form.dataset.creditLabel || 'este credito';

                if (!amount || amount <= 0) {
                    if (window.Swal) {
                        await Swal.fire({
                            icon: 'warning',
                            title: 'Falta el abono',
                            text: 'Ingresa un valor valido para registrar el pago.',
                            confirmButtonText: 'Aceptar',
                            confirmButtonColor: '#2563eb',
                        });
                    } else {
                        alert('Ingresa un valor valido para registrar el pago.');
                    }

                    return;
                }

                if (window.Swal) {
                    const result = await Swal.fire({
                        icon: 'question',
                        title: 'Confirmar pago',
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
