@php
    $brandName = 'Solomo & Pomo';
    $customerName = $sale->customer?->name ?: $sale->customer_name ?: 'Consumidor final';
    $notes = (string) ($sale->notes ?? '');
    $noteParts = collect(explode('|', $notes))
        ->map(fn ($part) => trim($part))
        ->filter()
        ->values();
    $address = $noteParts
        ->first(fn ($part) => str_starts_with($part, 'Direccion:'));
    $reference = $noteParts
        ->first(fn ($part) => str_starts_with($part, 'Referencia:'));
    $customerNotes = $noteParts
        ->first(fn ($part) => str_starts_with($part, 'Notas:'));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comanda domicilio #{{ $sale->id }} | {{ $brandName }}</title>
    <style>
        @page { size: 80mm auto; margin: 4mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 16px;
            background: #efefef;
            color: #111;
            font-family: "Segoe UI", Arial, sans-serif;
        }
        .ticket {
            width: min(100%, 302px);
            margin: 0 auto;
            padding: 18px 16px 20px;
            background: #fff;
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
            font-size: 25px;
            line-height: 1.05;
            font-weight: 800;
        }
        .ticket-number {
            margin-top: 8px;
            text-align: center;
            font-size: 20px;
            font-weight: 800;
        }
        .ticket-subtitle {
            margin-top: 4px;
            text-align: center;
            font-size: 13px;
            color: #575757;
        }
        .delivery-badge {
            margin-top: 12px;
            padding: 10px 8px;
            border: 2px solid #111;
            text-align: center;
            font-size: 18px;
            font-weight: 900;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .rule {
            margin: 14px 0;
            border-top: 1px dashed #d6d6d6;
        }
        .meta { display: grid; gap: 12px; }
        .meta-label {
            margin-bottom: 3px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: #666;
        }
        .meta-value {
            font-size: 16px;
            line-height: 1.18;
        }
        .item {
            display: grid;
            grid-template-columns: 40px 1fr;
            gap: 12px;
            padding: 12px 0;
            border-top: 1px dashed #ddd;
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
            color: #666;
        }
        .actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
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
            text-decoration: none;
        }
        .btn-primary { background: #111; color: #fff; }
        .btn-secondary { background: #e8e8e8; color: #111; }
        @media print {
            body { padding: 0; background: #fff; }
            .ticket {
                width: auto;
                max-width: none;
                border-radius: 0;
                box-shadow: none;
                padding: 0;
            }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="ticket">
        <div class="brand">{{ strtoupper($brandName) }}</div>
        <h1 class="title">Comanda</h1>
        <div class="ticket-number">Venta #{{ $sale->id }}</div>
        <div class="ticket-subtitle">Cobro manual de domicilio</div>
        <div class="delivery-badge">Domicilio para llevar</div>

        <div class="rule"></div>

        <div class="meta">
            <div>
                <div class="meta-label">Cliente</div>
                <div class="meta-value">{{ $customerName }}</div>
            </div>
            <div>
                <div class="meta-label">Cajero</div>
                <div class="meta-value">{{ $sale->user?->name ?? 'Equipo' }}</div>
            </div>
            <div>
                <div class="meta-label">Hora</div>
                <div class="meta-value">{{ $printedAt->format('d/m/Y H:i') }}</div>
            </div>
            @if($address)
                <div>
                    <div class="meta-label">Direccion</div>
                    <div class="meta-value">{{ trim(str_replace('Direccion:', '', $address)) }}</div>
                </div>
            @endif
            @if($reference)
                <div>
                    <div class="meta-label">Referencia</div>
                    <div class="meta-value">{{ trim(str_replace('Referencia:', '', $reference)) }}</div>
                </div>
            @endif
        </div>

        <div class="rule"></div>

        <div class="items">
            @foreach($sale->items as $item)
                <div class="item">
                    <div class="qty">{{ number_format($item->quantity) }}x</div>
                    <div>
                        <div class="name">{{ $item->product_name }}</div>
                        <div class="item-meta">Producto para cocina</div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="rule"></div>

        <div>
            <div class="meta-label">Notas</div>
            <div class="meta-value">{{ $customerNotes ? trim(str_replace('Notas:', '', $customerNotes)) : 'Sin notas' }}</div>
        </div>
    </div>

    <div class="actions">
        <button class="btn btn-secondary" type="button" onclick="handleClose()">Cerrar</button>
        <a class="btn btn-secondary" href="{{ $documentUrl }}" target="_blank" rel="noopener noreferrer">Factura</a>
        <button class="btn btn-primary" type="button" onclick="window.print()">Imprimir</button>
    </div>

    <script>
        function handleClose() {
            window.close();

            setTimeout(function () {
                if (!window.closed) {
                    window.location.href = @json($returnTo);
                }
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
