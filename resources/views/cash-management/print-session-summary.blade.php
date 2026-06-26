<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Tirilla cierre - {{ $session->box?->name ?? 'Caja' }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            color: #111827;
            margin: 0;
            background: #f3f4f6;
        }
        .actions {
            display: flex;
            gap: 8px;
            justify-content: center;
            padding: 14px;
        }
        button {
            border: 1px solid #111827;
            border-radius: 4px;
            cursor: pointer;
            padding: 8px 12px;
        }
        .btn-primary { background: #111827; color: #fff; }
        .btn-secondary { background: #fff; color: #111827; }
        .receipt {
            width: 80mm;
            margin: 0 auto 20px;
            background: #fff;
            padding: 12px;
        }
        .center { text-align: center; }
        h1 {
            font-size: 16px;
            margin: 0 0 6px;
            text-transform: uppercase;
        }
        .muted {
            color: #6b7280;
            font-size: 11px;
            line-height: 1.4;
        }
        .rule {
            border-top: 1px dashed #9ca3af;
            margin: 10px 0;
        }
        .row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            padding: 6px 0;
            font-size: 12px;
        }
        .row strong { text-align: right; white-space: nowrap; }
        .total {
            font-weight: 700;
            border-top: 1px solid #d1d5db;
            border-bottom: 1px solid #d1d5db;
            margin: 4px 0;
            padding: 8px 0;
        }
        .negative { color: inherit; }
        @media print {
            body { background: #fff; }
            .actions { display: none; }
            .receipt {
                width: 80mm;
                margin: 0;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="actions">
        <button class="btn-secondary" type="button" onclick="window.close()">Cerrar</button>
        <button class="btn-primary" type="button" onclick="window.print()">Imprimir</button>
    </div>

    <main class="receipt">
        <div class="center">
            <h1>SOLOMO & POMO</h1>
            <div class="muted">Tirilla general de cierre</div>
            <div class="muted">{{ $session->box?->name ?? 'Caja' }}</div>
        </div>

        <div class="rule"></div>

        <div class="row">
            <span>Responsable</span>
            <strong>{{ $session->user?->name ?? 'Sin responsable' }}</strong>
        </div>
        <div class="row">
            <span>Apertura</span>
            <strong>{{ $session->opened_at?->format('d/m/Y H:i') ?? '-' }}</strong>
        </div>
        <div class="row">
            <span>Cierre</span>
            <strong>{{ $session->closed_at?->format('d/m/Y H:i') ?? 'Sesion abierta' }}</strong>
        </div>
        <div class="row">
            <span>Impresion</span>
            <strong>{{ $printedAt->format('d/m/Y H:i') }}</strong>
        </div>

        <div class="rule"></div>

        <div class="row">
            <span>Base inicial</span>
            <strong>${{ money($summary['opening_balance']) }}</strong>
        </div>
        <div class="row">
            <span>Entradas en efectivo</span>
            <strong>${{ money($summary['physical_income']) }}</strong>
        </div>
        <div class="row">
            <span>Salidas en efectivo</span>
            <strong class="negative">-${{ money($summary['physical_expense']) }}</strong>
        </div>
        <div class="row total">
            <span>Saldo esperado en efectivo</span>
            <strong>${{ money($summary['expected_balance']) }}</strong>
        </div>
        <div class="row">
            <span>Valor contado en efectivo</span>
            <strong>${{ money($session->counted_balance ?? 0) }}</strong>
        </div>
        <div class="row">
            <span>Diferencia de efectivo</span>
            @php($difference = money_value((float) ($session->difference_amount ?? 0)))
            <strong class="{{ $difference < 0 ? 'negative' : '' }}">{{ $difference < 0 ? '-' : '' }}${{ money(abs($difference)) }}</strong>
        </div>
        <div class="row">
            <span>Transferencias informadas</span>
            <strong>${{ money($summary['transfer_total']) }}</strong>
        </div>

        <div class="rule"></div>
        <div class="muted">Observaciones</div>
        <div class="row">
            <span>{{ $session->closing_notes ?: 'Sin observaciones' }}</span>
        </div>

        <div class="rule"></div>
        <div class="center muted">Fin del cierre</div>
    </main>

    <script>
        window.addEventListener('load', function () {
            setTimeout(function () {
                window.print();
            }, 300);
        });
    </script>
</body>
</html>
