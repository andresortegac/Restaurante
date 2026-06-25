<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Tirilla movimiento #{{ $movement->id }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111; margin: 0; }
        .receipt { width: 280px; padding: 10px; }
        .center { text-align: center; }
        .brand { font-weight: 800; letter-spacing: 4px; font-size: 12px; }
        h1 { font-size: 18px; margin: 8px 0 4px; }
        .muted { color: #555; font-size: 11px; }
        .line { border-top: 1px dashed #aaa; margin: 10px 0; }
        .row { display: flex; justify-content: space-between; gap: 10px; margin: 5px 0; font-size: 12px; }
        .row strong { text-align: right; }
        .total { font-size: 16px; font-weight: 800; }
        .detail { font-size: 12px; margin-top: 8px; word-break: break-word; }
        .actions { margin: 10px; }
        button { padding: 8px 12px; border: 1px solid #111; background: #111; color: white; border-radius: 4px; cursor: pointer; }
        @media print {
            .actions { display: none; }
            @page { margin: 4mm; size: 80mm auto; }
        }
    </style>
</head>
<body>
    @php
        $labels = [
            'sale_income' => 'Venta POS',
            'table_order_payment' => 'Consumo de mesa',
            'manual_payment' => 'Cobro manual',
            'delivery_payment' => 'Domicilio',
            'credit_payment' => 'Pago de credito',
            'customer_credit_payment' => 'Pago de cartera',
            'customer_balance_payment' => 'Pago de saldo del cliente',
            'manual_income' => 'Ingreso manual',
            'manual_expense' => 'Egreso manual',
        ];
        $displayAmount = (float) ($movement->display_amount ?? $movement->amount);
        $isExpense = $displayAmount < 0;
        $sale = $movement->sale;
        $invoice = $sale?->invoice;
    @endphp

    <div class="actions">
        <button onclick="window.print()">Imprimir</button>
        <button onclick="window.close()">Cerrar</button>
    </div>

    <div class="receipt">
        <div class="center">
            <div class="brand">SOLOMO & POMO</div>
            <h1>Tirilla de movimiento</h1>
            <div class="muted">Generado el {{ $printedAt->format('d/m/Y H:i') }}</div>
        </div>

        <div class="line"></div>

        <div class="row"><span>Movimiento</span><strong>#{{ $movement->id }}</strong></div>
        <div class="row"><span>Tipo</span><strong>{{ $labels[$movement->movement_type] ?? str_replace('_', ' ', $movement->movement_type) }}</strong></div>
        <div class="row"><span>Fecha</span><strong>{{ $movement->occurred_at?->format('d/m/Y H:i') ?? '-' }}</strong></div>
        <div class="row"><span>Caja</span><strong>{{ $movement->box?->name ?? 'Caja' }}</strong></div>
        <div class="row"><span>Metodo</span><strong>{{ $movement->display_payment_method ?? 'Sin metodo' }}</strong></div>
        <div class="row"><span>Responsable</span><strong>{{ $movement->user?->name ?? $movement->session?->user?->name ?? 'Sistema' }}</strong></div>

        @if($sale)
            <div class="line"></div>
            <div class="row"><span>Venta</span><strong>#{{ $sale->id }}</strong></div>
            @if($invoice)
                <div class="row"><span>Documento</span><strong>{{ $invoice->invoice_number }}</strong></div>
            @endif
            <div class="row"><span>Cliente</span><strong>{{ $sale->customer?->name ?: $sale->customer_name ?: 'Consumidor final' }}</strong></div>
        @endif

        <div class="line"></div>
        <div class="row total"><span>{{ $isExpense ? 'Salida' : 'Entrada' }}</span><strong>{{ $isExpense ? '-' : '' }}${{ money(abs($displayAmount)) }}</strong></div>

        @if($movement->description)
            <div class="detail">{{ $movement->description }}</div>
        @endif

        <div class="line"></div>
        <div class="center muted">Comprobante interno de caja</div>
    </div>
</body>
</html>
