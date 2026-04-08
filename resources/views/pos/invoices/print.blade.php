<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura {{ $invoice->invoice_number }}</title>
    <style>
        body {
            margin: 0;
            padding: 24px;
            font-family: Arial, sans-serif;
            background: #f4f6fb;
            color: #1f2937;
        }

        .invoice-shell {
            max-width: 820px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.12);
            overflow: hidden;
        }

        .invoice-header {
            padding: 28px 32px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #ffffff;
        }

        .invoice-header h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }

        .invoice-header p {
            margin: 0;
            opacity: 0.92;
        }

        .invoice-body {
            padding: 32px;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .meta-card {
            padding: 16px 18px;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            background: #f8fafc;
        }

        .meta-card strong,
        .summary-card strong {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748b;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }

        .items-table th,
        .items-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }

        .items-table th:last-child,
        .items-table td:last-child {
            text-align: right;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .summary-card {
            padding: 18px;
            border-radius: 14px;
            background: #eef2ff;
            border: 1px solid #c7d2fe;
        }

        .summary-card .value {
            font-size: 24px;
            font-weight: 700;
            color: #312e81;
        }

        .actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin: 24px auto 0;
            max-width: 820px;
        }

        .btn {
            border: 0;
            border-radius: 999px;
            padding: 12px 18px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-primary {
            background: #1d4ed8;
            color: #ffffff;
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #111827;
        }

        @media print {
            body {
                padding: 0;
                background: #ffffff;
            }

            .invoice-shell {
                max-width: none;
                box-shadow: none;
                border-radius: 0;
            }

            .actions {
                display: none;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 12px;
            }

            .invoice-body,
            .invoice-header {
                padding: 20px;
            }

            .meta-grid,
            .summary-grid {
                grid-template-columns: 1fr;
            }

            .actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-shell">
        <div class="invoice-header">
            <h1>{{ $invoice->invoice_type === 'boleta' ? 'Boleta' : 'Factura' }} {{ $invoice->invoice_number }}</h1>
            <p>Venta #{{ $sale->id }} registrada el {{ $sale->created_at?->format('d/m/Y H:i') }}</p>
        </div>

        <div class="invoice-body">
            <div class="meta-grid">
                <div class="meta-card">
                    <strong>Vendedor</strong>
                    <span>{{ $sale->user?->name ?? 'Sin usuario' }}</span>
                </div>
                <div class="meta-card">
                    <strong>Caja</strong>
                    <span>{{ $sale->box?->name ?? 'Sin caja' }}</span>
                </div>
                <div class="meta-card">
                    <strong>Metodo de pago</strong>
                    <span>{{ $sale->payments->pluck('paymentMethod.name')->filter()->join(', ') ?: 'Sin pago registrado' }}</span>
                </div>
                <div class="meta-card">
                    <strong>Estado</strong>
                    <span>{{ ucfirst($invoice->status) }}</span>
                </div>
            </div>

            <table class="items-table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Precio unitario</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sale->items as $item)
                        <tr>
                            <td>{{ $item->product?->name ?? 'Producto eliminado' }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>${{ number_format((float) $item->unit_price, 2) }}</td>
                            <td>${{ number_format((float) $item->subtotal, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="summary-grid">
                <div class="summary-card">
                    <strong>Subtotal</strong>
                    <div class="value">${{ number_format((float) $sale->subtotal, 2) }}</div>
                </div>
                <div class="summary-card">
                    <strong>Impuesto</strong>
                    <div class="value">${{ number_format((float) $sale->tax_amount, 2) }}</div>
                </div>
                <div class="summary-card">
                    <strong>Total</strong>
                    <div class="value">${{ number_format((float) $sale->total, 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="actions">
        <button class="btn btn-secondary" type="button" onclick="window.close()">Cerrar</button>
        <button class="btn btn-primary" type="button" onclick="window.print()">Imprimir</button>
    </div>

    <script>
        window.addEventListener('load', function () {
            setTimeout(function () {
                window.print();
            }, 300);
        });
    </script>
</body>
</html>
