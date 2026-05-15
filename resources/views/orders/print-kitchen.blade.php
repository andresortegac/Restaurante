@php
    $brandName = config('app.name', 'Solomo & Pomo');
    $printedAt = now();
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comanda {{ $order->order_number }} | {{ $brandName }}</title>
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

        .ticket {
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
            font-size: 26px;
            line-height: 1.05;
            font-weight: 800;
        }

        .ticket-number {
            margin-top: 8px;
            text-align: center;
            font-size: 21px;
            font-weight: 800;
            line-height: 1.1;
        }

        .ticket-subtitle {
            margin-top: 4px;
            text-align: center;
            font-size: 13px;
            color: #575757;
        }

        .rule {
            margin: 14px 0;
            border-top: 1px dashed #d6d6d6;
        }

        .meta {
            display: grid;
            gap: 12px;
        }

        .meta-label {
            margin-bottom: 3px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: #666666;
        }

        .meta-value {
            font-size: 17px;
            line-height: 1.15;
        }

        .items {
            display: grid;
        }

        .item {
            display: grid;
            grid-template-columns: 40px 1fr;
            gap: 12px;
            padding: 12px 0;
            border-top: 1px dashed #dddddd;
        }

        .item:first-child {
            border-top: 0;
            padding-top: 0;
        }

        .qty {
            font-size: 18px;
            font-weight: 800;
            line-height: 1.1;
        }

        .name {
            font-size: 16px;
            font-weight: 800;
            line-height: 1.12;
        }

        .item-meta {
            margin-top: 4px;
            font-size: 12px;
            color: #666666;
        }

        .note {
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px dashed #d6d6d6;
        }

        .note-copy {
            font-size: 14px;
            line-height: 1.35;
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
            body {
                padding: 0;
                background: #ffffff;
            }

            .ticket {
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
    <div class="ticket">
        <div class="brand">{{ strtoupper($brandName) }}</div>
        <h1 class="title">Comanda</h1>
        <div class="ticket-number">{{ $order->order_number }}</div>
        <div class="ticket-subtitle">{{ $isPartialTicket ? 'Solo productos agregados recientemente' : 'Pedido completo para cocina' }}</div>

        <div class="rule"></div>

        <div class="meta">
            <div>
                <div class="meta-label">Mesa</div>
                <div class="meta-value">{{ $order->table?->name ?? 'Sin mesa' }} - {{ $order->table?->code ?? 'N/A' }}</div>
            </div>
            <div>
                <div class="meta-label">Salon</div>
                <div class="meta-value">{{ $order->table?->area ?: 'Salon principal' }}</div>
            </div>
            <div>
                <div class="meta-label">Mesero</div>
                <div class="meta-value">{{ $order->openedBy?->name ?? 'Equipo' }}</div>
            </div>
            <div>
                <div class="meta-label">Hora</div>
                <div class="meta-value">{{ $printedAt->format('d/m/Y H:i') }}</div>
            </div>
        </div>

        <div class="rule"></div>

        <div class="items">
            @foreach($items as $item)
                <div class="item">
                    <div class="qty">{{ $item->quantity }}x</div>
                    <div>
                        <div class="name">{{ $item->product_name }}</div>
                        <div class="item-meta">{{ $item->product?->product_type === 'combo' ? 'Combo del menu' : 'Producto del menu' }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        @if($order->notes)
            <div class="note">
                <div class="meta-label">Notas</div>
                <div class="note-copy">{{ $order->notes }}</div>
            </div>
        @endif
    </div>

    <div class="actions">
        <button class="btn btn-secondary" type="button" onclick="handleClose()">Cerrar</button>
        <button class="btn btn-primary" type="button" onclick="window.print()">Imprimir</button>
    </div>

    <script>
        const fallbackCloseUrl = @json(route('orders.history.index'));

        function handleClose() {
            window.close();

            setTimeout(function () {
                if (window.closed) {
                    return;
                }

                if (window.history.length > 1) {
                    window.history.back();
                    return;
                }

                if (document.referrer) {
                    window.location.href = document.referrer;
                    return;
                }

                window.location.href = fallbackCloseUrl;
            }, 150);
        }

        window.addEventListener('load', function () {
            setTimeout(function () {
                window.print();
            }, 250);
        });
    </script>
</body>
</html>
