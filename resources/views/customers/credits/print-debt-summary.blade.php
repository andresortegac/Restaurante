@php
    $brandName = 'Solomo & Pomo';
    $pendingTotal = (float) ($summary['pending'] ?? 0);
    $availableBalance = (float) ($summary['available'] ?? 0);
    $netToCollect = max($pendingTotal - $availableBalance, 0);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tirilla de deuda - {{ $customer->name }} | {{ $brandName }}</title>
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

        .subtitle {
            margin-top: 6px;
            text-align: center;
            font-size: 12px;
            color: #575757;
        }

        .rule {
            margin: 14px 0;
            border-top: 1px dashed #d6d6d6;
        }

        .meta,
        .summary,
        .debts {
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
            font-size: 17px;
            font-weight: 800;
        }

        .debt {
            padding-top: 10px;
            border-top: 1px dashed #dddddd;
        }

        .debt:first-child {
            padding-top: 0;
            border-top: 0;
        }

        .debt-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
        }

        .debt-title {
            font-size: 13px;
            line-height: 1.25;
            font-weight: 800;
        }

        .debt-amount {
            font-size: 14px;
            line-height: 1.2;
            font-weight: 800;
            white-space: nowrap;
        }

        .debt-meta {
            margin-top: 4px;
            font-size: 11px;
            line-height: 1.35;
            color: #666666;
        }

        .empty {
            text-align: center;
            font-size: 13px;
            line-height: 1.4;
            color: #555555;
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
        <h1 class="title">Deuda cliente</h1>
        <div class="subtitle">Generado el {{ $printedAt->format('d/m/Y H:i') }}</div>

        <div class="rule"></div>

        <div class="meta">
            <div class="row">
                <span>Cliente</span>
                <strong>{{ $customer->name }}</strong>
            </div>
            <div class="row">
                <span>Documento</span>
                <strong>{{ $customer->document_number ?: 'Sin documento' }}</strong>
            </div>
            @if($customer->phone)
                <div class="row">
                    <span>Telefono</span>
                    <strong>{{ $customer->phone }}</strong>
                </div>
            @endif
        </div>

        <div class="rule"></div>

        <div class="summary">
            <div class="row">
                <span>Creditos pendientes</span>
                <strong>{{ number_format($summary['pendingCount'] ?? $credits->count()) }}</strong>
            </div>
            <div class="row">
                <span>Total pendiente</span>
                <strong>${{ money($pendingTotal) }}</strong>
            </div>
            <div class="row">
                <span>Saldo a favor</span>
                <strong>${{ money($availableBalance) }}</strong>
            </div>
            <div class="row total">
                <span>Total a cobrar</span>
                <strong>${{ money($netToCollect) }}</strong>
            </div>
        </div>

        <div class="rule"></div>

        <div class="debts">
            @forelse($credits as $credit)
                <div class="debt">
                    <div class="debt-head">
                        <div class="debt-title">{{ $credit->description ?: 'Saldo pendiente' }}</div>
                        <div class="debt-amount">${{ money($credit->balance) }}</div>
                    </div>
                    <div class="debt-meta">
                        {{ $credit->created_at?->format('d/m/Y') }}
                        @if($credit->due_at)
                            | Vence {{ $credit->due_at->format('d/m/Y') }}
                        @endif
                        <br>
                        {{ $credit->sale_id ? 'Venta #' . $credit->sale_id : 'Registro manual' }}
                        @if($credit->sale?->tableOrder?->order_number)
                            | {{ $credit->sale->tableOrder->order_number }}
                        @endif
                        @if($credit->sale?->tableOrder?->table?->name)
                            | {{ $credit->sale->tableOrder->table->name }}
                        @endif
                    </div>
                </div>
            @empty
                <div class="empty">El cliente no tiene deuda pendiente.</div>
            @endforelse
        </div>

        <div class="thanks">Resumen de deuda</div>
        <div class="paper-feed" aria-hidden="true"></div>
    </div>

    <div class="actions">
        <button class="btn btn-secondary" type="button" onclick="handleClose()">Cerrar</button>
        <button class="btn btn-primary" type="button" onclick="window.print()">Imprimir</button>
    </div>

    <script>
        const fallbackCloseUrl = @json(route('customers.credits.show', $customer));

        function handleClose() {
            window.close();

            setTimeout(function () {
                if (window.closed) {
                    return;
                }

                window.location.href = fallbackCloseUrl;
            }, 150);
        }

        window.addEventListener('load', function () {
            setTimeout(function () {
                window.print();
            }, 300);
        });
    </script>
</body>
</html>
