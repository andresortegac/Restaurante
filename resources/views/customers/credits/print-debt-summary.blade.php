<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Resumen de deuda - {{ $customer->name }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 24px; }
        h1, h2 { margin: 0 0 8px; }
        .muted { color: #6b7280; font-size: 12px; }
        .header { display: flex; justify-content: space-between; gap: 24px; border-bottom: 1px solid #d1d5db; padding-bottom: 16px; margin-bottom: 18px; }
        .summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 18px 0; }
        .box { border: 1px solid #d1d5db; padding: 10px; border-radius: 6px; }
        .label { color: #6b7280; font-size: 11px; text-transform: uppercase; }
        .value { font-size: 18px; font-weight: 700; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; font-size: 12px; text-transform: uppercase; }
        .right { text-align: right; }
        .actions { margin-bottom: 18px; }
        button { padding: 8px 12px; border: 1px solid #111827; background: #111827; color: white; border-radius: 4px; cursor: pointer; }
        @media print {
            .actions { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="actions">
        <button onclick="window.print()">Imprimir</button>
    </div>

    <div class="header">
        <div>
            <h1>Resumen detallado de deuda</h1>
            <div class="muted">Generado el {{ $printedAt->format('d/m/Y H:i') }}</div>
        </div>
        <div>
            <h2>{{ $customer->name }}</h2>
            <div class="muted">Documento: {{ $customer->document_number ?: 'Sin documento' }}</div>
            <div class="muted">Telefono: {{ $customer->phone ?: 'Sin telefono' }}</div>
            <div class="muted">Email: {{ $customer->email ?: 'Sin email' }}</div>
        </div>
    </div>

    <div class="summary">
        <div class="box">
            <div class="label">Total pendiente</div>
            <div class="value">${{ money($summary['pending']) }}</div>
        </div>
        <div class="box">
            <div class="label">Creditos pendientes</div>
            <div class="value">{{ number_format($summary['pendingCount']) }}</div>
        </div>
        <div class="box">
            <div class="label">Saldo a favor disponible</div>
            <div class="value">${{ money($summary['available']) }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Concepto</th>
                <th>Origen</th>
                <th class="right">Valor original</th>
                <th class="right">Saldo pendiente</th>
            </tr>
        </thead>
        <tbody>
            @forelse($credits as $credit)
                <tr>
                    <td>
                        {{ $credit->created_at?->format('d/m/Y') }}
                        <div class="muted">{{ $credit->due_at ? 'Vence: ' . $credit->due_at->format('d/m/Y') : 'Sin vencimiento' }}</div>
                    </td>
                    <td>{{ $credit->description ?: 'Saldo pendiente' }}</td>
                    <td>
                        {{ $credit->sale_id ? 'Venta #' . $credit->sale_id : 'Registro manual' }}
                        @if($credit->sale?->tableOrder?->order_number)
                            <div class="muted">{{ $credit->sale->tableOrder->order_number }}{{ $credit->sale->tableOrder->table?->name ? ' | ' . $credit->sale->tableOrder->table->name : '' }}</div>
                        @endif
                    </td>
                    <td class="right">${{ money($credit->amount) }}</td>
                    <td class="right">${{ money($credit->balance) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">El cliente no tiene deuda pendiente.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
