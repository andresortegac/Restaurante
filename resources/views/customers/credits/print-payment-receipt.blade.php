@php
    $brandName = 'SOLOMO & POMO';
    $paymentType = money_value($receipt->remaining_pending) <= 0 ? 'Pago completo' : 'Abono';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo de pago {{ $receipt->receipt_number }} | {{ $brandName }}</title>
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
            font-size: 18px;
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

        .rows {
            display: grid;
            gap: 8px;
        }

        .row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            font-size: 13px;
            line-height: 1.35;
        }

        .row span {
            color: #666666;
        }

        .row strong {
            text-align: right;
            font-weight: 700;
            color: #111111;
        }

        .total {
            padding-top: 10px;
            border-top: 1px dashed #d6d6d6;
            font-size: 16px;
            font-weight: 800;
        }

        .item {
            padding-top: 10px;
            border-top: 1px dashed #dddddd;
            font-size: 12px;
        }

        .item:first-child {
            padding-top: 0;
            border-top: 0;
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
        <h1 class="title">Recibo de pago</h1>
        <div class="number">{{ $receipt->receipt_number }}</div>
        <div class="subtitle">{{ $receipt->paid_at?->format('d/m/Y H:i') }}</div>
        <div class="business-info">
            <div>NIT 1083927195-1</div>
            <div>CRA 33 NO 14A-25</div>
            <div>ZONA ROSA - PTO ASIS</div>
            <div>CEL 3142181805 - 3209447915</div>
        </div>

        <div class="rule"></div>

        <div class="rows">
            <div class="row">
                <span>Cliente</span>
                <strong>{{ $customer->name }}</strong>
            </div>
            <div class="row">
                <span>Documento</span>
                <strong>{{ $customer->document_number ?: 'Sin documento' }}</strong>
            </div>
            <div class="row">
                <span>Metodo</span>
                <strong>{{ $receipt->paymentMethod?->name ?: 'Efectivo' }}</strong>
            </div>
            @if($receipt->reference)
                <div class="row">
                    <span>Nota</span>
                    <strong>{{ $receipt->reference }}</strong>
                </div>
            @endif
        </div>

        <div class="rule"></div>

        <div class="rows">
            <div class="row">
                <span>Tipo de pago</span>
                <strong>{{ $paymentType }}</strong>
            </div>
            <div class="row total">
                <span>Valor pagado</span>
                <strong>${{ money($receipt->amount) }}</strong>
            </div>
            <div class="row">
                <span>Saldo pendiente</span>
                <strong>${{ money($receipt->remaining_pending) }}</strong>
            </div>
        </div>

        <div class="thanks">Gracias por su pago</div>
        <div class="paper-feed" aria-hidden="true"></div>
    </div>

    <div class="actions">
        <button class="btn btn-secondary" type="button" onclick="handleClose()">Cerrar</button>
        <button class="btn btn-primary" type="button" onclick="window.print()">Imprimir</button>
    </div>

    <script>
        const fallbackCloseUrl = @json(route('customers.credits.payments.history', $customer));

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
