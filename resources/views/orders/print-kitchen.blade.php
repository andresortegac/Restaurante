<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comanda {{ $order->order_number }}</title>
    <style>
        body { margin: 0; padding: 20px; font-family: Arial, sans-serif; background: #f8fafc; color: #0f172a; }
        .ticket { max-width: 420px; margin: 0 auto; background: #fff; border: 1px dashed #94a3b8; border-radius: 16px; padding: 24px; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08); }
        .header { text-align: center; padding-bottom: 16px; border-bottom: 1px dashed #cbd5e1; }
        .header h1 { margin: 0 0 8px; font-size: 28px; }
        .muted { color: #64748b; font-size: 13px; }
        .meta { display: grid; gap: 10px; margin: 18px 0; }
        .meta strong { display: block; font-size: 12px; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; }
        .items { margin-top: 18px; border-top: 1px dashed #cbd5e1; }
        .item { display: grid; grid-template-columns: 70px 1fr; gap: 12px; padding: 14px 0; border-bottom: 1px dashed #e2e8f0; }
        .qty { font-size: 28px; font-weight: 700; text-align: center; }
        .name { font-size: 18px; font-weight: 700; }
        .note { margin-top: 18px; padding: 12px 14px; background: #eff6ff; border-radius: 12px; }
        .actions { display: flex; gap: 12px; justify-content: center; margin: 20px auto 0; max-width: 420px; }
        .btn { border: 0; border-radius: 999px; padding: 12px 18px; font-weight: 700; cursor: pointer; }
        .btn-primary { background: #1d4ed8; color: #fff; }
        .btn-secondary { background: #e2e8f0; color: #0f172a; }
        @media print {
            body { padding: 0; background: #fff; }
            .ticket { box-shadow: none; border: 0; border-radius: 0; max-width: none; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="ticket">
        <div class="header">
            <h1>Comanda</h1>
            <div><strong>{{ $order->order_number }}</strong></div>
            <div class="muted">{{ $isPartialTicket ? 'Solo productos agregados recientemente' : 'Pedido completo para cocina' }}</div>
        </div>

        <div class="meta">
            <div><strong>Mesa</strong><span>{{ $order->table?->name ?? 'Sin mesa' }} - {{ $order->table?->code ?? 'N/A' }}</span></div>
            <div><strong>Salon</strong><span>{{ $order->table?->area ?: 'Salon principal' }}</span></div>
            <div><strong>Mesero</strong><span>{{ $order->openedBy?->name ?? 'Equipo' }}</span></div>
            <div><strong>Hora</strong><span>{{ now()->format('d/m/Y H:i') }}</span></div>
        </div>

        <div class="items">
            @foreach($items as $item)
                <div class="item">
                    <div class="qty">{{ $item->quantity }}x</div>
                    <div>
                        <div class="name">{{ $item->product_name }}</div>
                        <div class="muted">{{ $item->product?->product_type === 'combo' ? 'Combo' : 'Producto del menu' }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        @if($order->notes)
            <div class="note">
                <strong>Notas:</strong> {{ $order->notes }}
            </div>
        @endif
    </div>

    <div class="actions">
        <button class="btn btn-secondary" type="button" onclick="window.close()">Cerrar</button>
        <button class="btn btn-primary" type="button" onclick="window.print()">Imprimir</button>
    </div>

    <script>
        window.addEventListener('load', function () {
            setTimeout(function () {
                window.print();
            }, 250);
        });
    </script>
</body>
</html>
