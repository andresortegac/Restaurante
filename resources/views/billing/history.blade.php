@extends('layouts.app')

@section('title', 'Ventas Generales - Facturacion')

@push('styles')
    <style>
        .billing-history-pagination .pagination {
            align-items: center;
            flex-wrap: wrap;
            gap: 0.35rem;
            margin-bottom: 0;
        }

        .billing-history-pagination .page-link {
            min-width: 34px;
            min-height: 34px;
            padding: 0.35rem 0.65rem;
            border-radius: 10px;
            font-size: 0.85rem;
            line-height: 1.1;
        }

        .billing-history-pagination .page-item:first-child .page-link,
        .billing-history-pagination .page-item:last-child .page-link {
            padding-inline: 0.75rem;
        }
    </style>
@endpush

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
                <span class="summary-chip">${{ money($summary['revenue']) }} vendidos</span>
                <span class="summary-chip">{{ number_format($summary['electronic']) }} electronicas</span>
                <span class="summary-chip">${{ money($summary['credit']) }} en credito</span>
                @if(Auth::user()->hasRole('Admin'))
                    <a href="{{ route('billing.voided') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-ban"></i> Facturas anuladas
                    </a>
                @endif
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
                                        $appliedCustomerBalance = $sale->customerBalanceAppliedTotal();
                                        $receivedAmount = $sale->externalReceivedTotal();
                                        $changeAmount = $sale->paymentChangeTotal();
                                        $tipAmount = $sale->paymentTipTotal();
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
                                                    <div class="text-muted small">Saldo pendiente: ${{ money($remainingCreditBalance) }}</div>
                                                    <div class="text-muted small">Abonado: ${{ money(max(0, (float) $sale->total - $remainingCreditBalance)) }}</div>
                                                @else
                                                    <strong>{{ $sale->paymentMethodSummary() ?: 'Sin pago' }}</strong>
                                                    @if($appliedCustomerBalance > 0)
                                                        <div class="text-muted small">Saldo a favor aplicado: ${{ money($appliedCustomerBalance) }}</div>
                                                    @endif
                                                    <div class="text-muted small">Recibido: ${{ money($receivedAmount) }}</div>
                                                    <div class="text-muted small">Cambio: ${{ money($changeAmount) }}</div>
                                                    <div class="text-muted small">Propina: ${{ money($tipAmount) }}</div>
                                                @endif
                                            @else
                                                <span class="text-muted">Sin pago</span>
                                            @endif
                                        </td>
                                        <td>
                                            <strong>${{ money($sale->total) }}</strong>
                                            @if($tipAmount > 0)
                                                <div class="text-muted small">Con propina: ${{ money((float) $sale->total + $tipAmount) }}</div>
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
                                                        <input type="number" name="amount_received" class="form-control form-control-sm" min="1" max="{{ money_input($remainingCreditBalance) }}" step="1" value="{{ money_input($remainingCreditBalance) }}" style="width: 120px;">
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
                                                @if($sale->tableOrder && $sale->canBeEditedInOpenCashSession())
                                                    <a href="{{ route('orders.edit', $sale->tableOrder) }}" class="btn btn-sm btn-outline-secondary">
                                                        <i class="fas fa-pen"></i> Editar pedido
                                                    </a>
                                                @elseif($sale->canBeEditedInOpenCashSession())
                                                    <a href="{{ route('billing.sales.edit', $sale) }}" class="btn btn-sm btn-outline-secondary">
                                                        <i class="fas fa-pen"></i> Editar factura
                                                    </a>
                                                @endif
                                                @if(Auth::user()->hasRole('Admin'))
                                                    <form method="POST" action="{{ route('billing.sales.void', $sale) }}" class="m-0" data-void-sale-form data-sale-label="factura #{{ $invoice?->invoice_number ?? $sale->id }}">
                                                        @csrf
                                                        <input type="hidden" name="void_reason">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <i class="fas fa-ban"></i> Anular
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="billing-history-pagination mt-3">
                        {{ $sales->links('pagination::bootstrap-5') }}
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
                        text: 'Se registrara un abono de $' + Math.round(amount).toLocaleString('es-CO') + ' para ' + label + '.',
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

        document.querySelectorAll('[data-void-sale-form]').forEach(function (form) {
            form.addEventListener('submit', async function (event) {
                event.preventDefault();

                const label = form.dataset.saleLabel || 'esta factura';
                let reason = '';

                if (window.Swal) {
                    const result = await Swal.fire({
                        icon: 'warning',
                        title: 'Anular factura',
                        text: 'La venta dejara de sumar en caja y no aparecera en el cierre. Esta accion queda registrada.',
                        input: 'text',
                        inputLabel: 'Motivo',
                        inputPlaceholder: 'Ej: Pedido de prueba',
                        inputValidator: (value) => value && value.trim().length ? undefined : 'Escribe el motivo de anulacion.',
                        showCancelButton: true,
                        confirmButtonText: 'Anular',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                    });

                    if (!result.isConfirmed) {
                        return;
                    }

                    reason = String(result.value || '').trim();
                } else {
                    reason = prompt('Motivo para anular ' + label + ':') || '';
                    reason = reason.trim();

                    if (!reason) {
                        return;
                    }
                }

                form.querySelector('input[name="void_reason"]').value = reason;
                form.submit();
            });
        });
    });
    </script>
@endsection
