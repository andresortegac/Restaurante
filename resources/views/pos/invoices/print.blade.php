@php
    $brandName = config('app.name', 'Solomo & Pomo');
    $documentTitle = $invoice->isElectronic() ? 'Factura electronica' : 'Recibo de caja';
    $customerName = $sale->customer?->name ?: $sale->customer_name;
    $paymentMethods = $sale->paymentMethodSummary();
    $receivedAmount = $sale->externalReceivedTotal();
    $changeAmount = $sale->paymentChangeTotal();
    $tipAmount = $sale->paymentTipTotal();
    $appliedCustomerBalance = $sale->customerBalanceAppliedTotal();
    $itemsCount = (float) $sale->items->sum('quantity');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $documentTitle }} {{ $invoice->invoice_number }} | {{ $brandName }}</title>
    <style>
        :root {
            color-scheme: light;
        }

        @page {
            size: 80mm auto;
            margin: 4mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 16px;
            background: #efefef;
            color: #111111;
            font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
        }

        .receipt {
            width: min(100%, 302px);
            margin: 0 auto;
            padding: 18px 16px 20px;
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 14px 32px rgba(17, 17, 17, 0.10);
        }

        .brand {
            margin-bottom: 10px;
            text-align: center;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.28em;
            text-transform: uppercase;
        }

        .title {
            margin: 0;
            text-align: center;
            font-size: 24px;
            line-height: 1.08;
            font-weight: 800;
        }

        .number {
            margin-top: 8px;
            text-align: center;
            font-size: 21px;
            font-weight: 800;
            line-height: 1.1;
        }

        .subtitle {
            margin-top: 4px;
            text-align: center;
            font-size: 12px;
            color: #575757;
        }

        .business-info {
            margin-top: 10px;
            text-align: center;
            font-size: 11px;
            line-height: 1.45;
            font-weight: 700;
            color: #222222;
        }

        .rule {
            margin: 14px 0;
            border-top: 1px dashed #d6d6d6;
        }

        .meta {
            display: grid;
            gap: 8px;
        }

        .meta-row,
        .summary-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            font-size: 13px;
            line-height: 1.35;
        }

        .meta-row span,
        .summary-row span {
            color: #666666;
        }

        .meta-row strong,
        .summary-row strong {
            text-align: right;
            font-weight: 700;
            color: #111111;
        }

        .items {
            display: grid;
            gap: 12px;
        }

        .item {
            padding-top: 12px;
            border-top: 1px dashed #dddddd;
        }

        .item:first-child {
            padding-top: 0;
            border-top: 0;
        }

        .item-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
        }

        .item-name {
            font-size: 14px;
            line-height: 1.25;
            font-weight: 700;
        }

        .item-qty {
            margin-right: 6px;
            font-weight: 800;
        }

        .item-total {
            font-size: 14px;
            line-height: 1.2;
            font-weight: 800;
            white-space: nowrap;
        }

        .item-sub {
            margin-top: 4px;
            font-size: 12px;
            color: #666666;
        }

        .summary {
            display: grid;
            gap: 8px;
        }

        .summary-total {
            padding-top: 10px;
            border-top: 1px dashed #d6d6d6;
            font-size: 16px;
            font-weight: 800;
        }

        .footer-copy {
            font-size: 11px;
            line-height: 1.4;
            color: #555555;
            word-break: break-word;
        }

        .thanks {
            margin-top: 14px;
            text-align: center;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .paper-feed {
            height: 18mm;
        }

        .actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 16px auto 0;
            width: min(100%, 302px);
        }

        .btn {
            appearance: none;
            border: 0;
            border-radius: 999px;
            padding: 11px 16px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-primary {
            background: #111111;
            color: #ffffff;
        }

        .btn-secondary {
            background: #e8e8e8;
            color: #111111;
        }

        @media print {
            @page {
                size: 80mm auto;
                margin: 3mm 4mm 8mm;
            }

            html,
            body {
                width: 80mm;
                padding: 0;
                background: #ffffff;
            }

            .receipt {
                width: auto;
                max-width: none;
                border-radius: 0;
                box-shadow: none;
                padding: 0;
            }

            .actions {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="brand">{{ strtoupper($brandName) }}</div>
        <h1 class="title">{{ $documentTitle }}</h1>
        <div class="number">{{ $invoice->invoice_number }}</div>
        <div class="subtitle">{{ $sale->created_at?->format('d/m/Y H:i') }}</div>
        <div class="business-info">
            <div>NIT 1083927195-1</div>
            <div>CRA 33 NO 14A-25</div>
            <div>ZONA ROSA - PTO ASÍS</div>
            <div>CEL 3142181805 - 3209447915</div>
        </div>

        <div class="rule"></div>

        <div class="meta">
            @if($sale->tableOrder)
                <div class="meta-row">
                    <span>Mesa</span>
                    <strong>{{ $sale->tableOrder->table?->name ?? 'Sin mesa' }}</strong>
                </div>
                <div class="meta-row">
                    <span>Pedido</span>
                    <strong>{{ $sale->tableOrder->order_number }}</strong>
                </div>
            @elseif($sale->delivery)
                <div class="meta-row">
                    <span>Domicilio</span>
                    <strong>{{ $sale->delivery->delivery_number }}</strong>
                </div>
            @endif

            @if(filled($customerName) && $customerName !== 'Consumidor final')
                <div class="meta-row">
                    <span>Cliente</span>
                    <strong>{{ $customerName }}</strong>
                </div>
            @endif

            <div class="meta-row">
                <span>Pago</span>
                <strong>{{ $sale->payment_status === 'credit' ? 'Credito pendiente' : ($paymentMethods ?: 'Sin pago registrado') }}</strong>
            </div>
            @if($sale->payment_status === 'credit')
                <div class="meta-row">
                    <span>Vence</span>
                    <strong>{{ $sale->credit_due_at ? $sale->credit_due_at->format('d/m/Y') : 'Sin fecha' }}</strong>
                </div>
            @endif
        </div>

        <div class="rule"></div>

        <div class="items">
            @foreach($sale->items as $item)
                <div class="item">
                    <div class="item-head">
                        <div class="item-name">
                            <span class="item-qty">{{ $item->quantity }}x</span>
                            {{ $item->product_name ?: ($item->product?->name ?? 'Producto eliminado') }}
                        </div>
                        <div class="item-total">${{ money($item->subtotal) }}</div>
                    </div>
                    @if((float) $item->quantity > 1)
                        <div class="item-sub">${{ money($item->unit_price) }} c/u</div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="rule"></div>

        <div class="summary">
            <div class="summary-row">
                <span>Subtotal</span>
                <strong>${{ money($sale->subtotal) }}</strong>
            </div>

            <div class="summary-row">
                <span>Numero de articulos</span>
                <strong>{{ number_format($itemsCount, floor($itemsCount) === $itemsCount ? 0 : 2) }}</strong>
            </div>

            @if((float) $sale->discount_amount > 0)
                <div class="summary-row">
                    <span>Descuento</span>
                    <strong>-${{ money($sale->discount_amount) }}</strong>
                </div>
            @endif

            @if((float) $sale->tax_amount > 0)
                <div class="summary-row">
                    <span>Impuesto</span>
                    <strong>${{ money($sale->tax_amount) }}</strong>
                </div>
            @endif

            @if($tipAmount > 0)
                <div class="summary-row">
                    <span>Propina</span>
                    <strong>${{ money($tipAmount) }}</strong>
                </div>
            @endif

            <div class="summary-row summary-total">
                <span>Total</span>
                <strong>${{ money($sale->total) }}</strong>
            </div>

            @if($sale->payment_status === 'credit')
                <div class="summary-row">
                    <span>Saldo credito</span>
                    <strong>${{ money($sale->total) }}</strong>
                </div>
            @elseif($appliedCustomerBalance > 0)
                <div class="summary-row">
                    <span>Saldo a favor aplicado</span>
                    <strong>${{ money($appliedCustomerBalance) }}</strong>
                </div>
            @elseif($receivedAmount > 0)
                <div class="summary-row">
                    <span>Recibido</span>
                    <strong>${{ money($receivedAmount) }}</strong>
                </div>
            @endif

            @if($appliedCustomerBalance > 0 && $receivedAmount > 0)
                <div class="summary-row">
                    <span>Recibido</span>
                    <strong>${{ money($receivedAmount) }}</strong>
                </div>
            @endif

            @if($receivedAmount > 0 || $changeAmount > 0)
                <div class="summary-row">
                    <span>Su cambio</span>
                    <strong>${{ money($changeAmount) }}</strong>
                </div>
            @endif
        </div>

        @if($invoice->isElectronic() && $invoice->cufe)
            <div class="rule"></div>
            <div class="footer-copy">
                <strong>CUFE:</strong><br>
                {{ $invoice->cufe }}
            </div>
        @endif

        <div class="thanks">Gracias por su compra</div>
        <div class="paper-feed" aria-hidden="true"></div>
    </div>

    <div class="actions">
        <button class="btn btn-secondary" type="button" onclick="handleClose()">Cerrar</button>
        <button class="btn btn-primary" type="button" onclick="window.print()">Imprimir</button>
    </div>

    <script>
        const fallbackCloseUrl = @json($sale->delivery ? route('deliveries.index') : route('billing.history'));

        function handleClose() {
            window.location.href = fallbackCloseUrl;
        }

        window.addEventListener('load', function () {
            setTimeout(function () {
                window.print();
            }, 300);
        });
    </script>
</body>
</html>
