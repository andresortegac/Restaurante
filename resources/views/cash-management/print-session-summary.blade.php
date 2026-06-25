<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Resumen de cierre - {{ $session->box?->name ?? 'Caja' }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 24px; }
        h1, h2 { margin: 0 0 8px; }
        .muted { color: #6b7280; font-size: 12px; }
        .header { display: flex; justify-content: space-between; gap: 24px; border-bottom: 1px solid #d1d5db; padding-bottom: 16px; margin-bottom: 18px; }
        .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin: 18px 0; }
        .box { border: 1px solid #d1d5db; padding: 10px; border-radius: 6px; }
        .label { color: #6b7280; font-size: 11px; text-transform: uppercase; }
        .value { font-size: 17px; font-weight: 700; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 7px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; font-size: 11px; text-transform: uppercase; }
        .right { text-align: right; }
        .section { margin-top: 22px; }
        .actions { margin-bottom: 18px; }
        button { padding: 8px 12px; border: 1px solid #111827; background: #111827; color: white; border-radius: 4px; cursor: pointer; }
        @media print {
            .actions { display: none; }
            body { margin: 0; }
            .summary { grid-template-columns: repeat(4, 1fr); }
        }
    </style>
</head>
<body>
    <div class="actions">
        <button onclick="window.print()">Imprimir</button>
    </div>

    <div class="header">
        <div>
            <h1>Resumen detallado de cierre</h1>
            <div class="muted">Generado el {{ $printedAt->format('d/m/Y H:i') }}</div>
        </div>
        <div>
            <h2>{{ $session->box?->name ?? 'Caja' }}</h2>
            <div class="muted">Responsable: {{ $session->user?->name ?? 'Sin responsable' }}</div>
            <div class="muted">Apertura: {{ $session->opened_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}</div>
            <div class="muted">Cierre: {{ $session->closed_at?->format('d/m/Y H:i') ?? 'Sesion abierta' }}</div>
        </div>
    </div>

    <div class="summary">
        <div class="box">
            <div class="label">Base inicial</div>
            <div class="value">${{ money($summary['opening_balance']) }}</div>
        </div>
        <div class="box">
            <div class="label">Entradas caja fisica</div>
            <div class="value">${{ money($summary['physical_income']) }}</div>
        </div>
        <div class="box">
            <div class="label">Egresos caja fisica</div>
            <div class="value">${{ money($summary['physical_expense']) }}</div>
        </div>
        <div class="box">
            <div class="label">Saldo esperado</div>
            <div class="value">${{ money($summary['expected_balance']) }}</div>
        </div>
        <div class="box">
            <div class="label">Valor contado</div>
            <div class="value">${{ money($session->counted_balance ?? 0) }}</div>
        </div>
        <div class="box">
            <div class="label">Diferencia</div>
            <div class="value">${{ money($session->difference_amount ?? 0) }}</div>
        </div>
        <div class="box">
            <div class="label">Total por metodos</div>
            <div class="value">${{ money($summary['reported_payment_total']) }}</div>
        </div>
        <div class="box">
            <div class="label">Movimientos</div>
            <div class="value">{{ number_format($summary['movement_count']) }}</div>
        </div>
    </div>

    <div class="section">
        <h2>Entradas por metodo de pago</h2>
        <table>
            <thead>
                <tr>
                    <th>Metodo</th>
                    <th class="right">Operaciones</th>
                    <th class="right">Total informado</th>
                </tr>
            </thead>
            <tbody>
                @forelse($paymentBreakdown as $paymentRow)
                    <tr>
                        <td>{{ $paymentRow['name'] }}</td>
                        <td class="right">{{ number_format($paymentRow['count']) }}</td>
                        <td class="right">${{ money($paymentRow['total']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3">No hay pagos asociados a esta sesion.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Detalle de movimientos</h2>
        <table>
            <thead>
                <tr>
                    <th>Hora</th>
                    <th>Movimiento</th>
                    <th>Venta / documento</th>
                    <th>Cliente</th>
                    <th>Descripcion</th>
                    <th class="right">Valor</th>
                </tr>
            </thead>
            <tbody>
                @forelse($movements as $movement)
                    @php
                        $sale = $movement->sale;
                        $invoice = $sale?->invoice;
                        $displayAmount = (float) ($movement->display_amount ?? $movement->amount);
                    @endphp
                    <tr>
                        <td>{{ $movement->occurred_at?->format('H:i') ?? '-' }}</td>
                        <td>{{ str_replace('_', ' ', $movement->movement_type) }}</td>
                        <td>
                            {{ $sale ? 'Venta #' . $sale->id : 'Sin venta' }}
                            @if($invoice)
                                <div class="muted">{{ $invoice->invoice_number }} | {{ $invoice->isElectronic() ? 'Factura electronica' : 'Ticket' }}</div>
                            @endif
                        </td>
                        <td>{{ $sale?->customer?->name ?: $sale?->customer_name ?: 'Consumidor final' }}</td>
                        <td>{{ $movement->description ?: 'Sin detalle' }}</td>
                        <td class="right">{{ $displayAmount < 0 ? '-' : '' }}${{ money(abs($displayAmount)) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">No hay movimientos en esta sesion.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($session->closing_notes)
        <div class="section">
            <h2>Observaciones</h2>
            <p>{{ $session->closing_notes }}</p>
        </div>
    @endif
</body>
</html>
