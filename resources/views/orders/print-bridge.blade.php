<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Enviando a cocina' }}</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background: #f4f6fb; color: #1f2937; display: grid; place-items: center; min-height: 100vh; }
        .card { width: min(520px, calc(100vw - 32px)); background: #fff; border-radius: 18px; padding: 28px; box-shadow: 0 20px 50px rgba(15, 23, 42, 0.12); }
        h1 { margin-top: 0; }
        p { line-height: 1.5; color: #475569; }
        .actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 18px; }
        .btn { border: 0; border-radius: 999px; padding: 12px 18px; font-weight: 700; text-decoration: none; }
        .btn-primary { background: #1d4ed8; color: #fff; }
        .btn-secondary { background: #e2e8f0; color: #0f172a; }
    </style>
</head>
<body>
    <div class="card">
        <h1>{{ $title ?? 'Preparando comanda de cocina' }}</h1>
        <p>{{ $message ?? 'Estamos abriendo la impresion de cocina y en unos segundos volveras al pedido de la mesa.' }}</p>
        <div class="actions">
            <a class="btn btn-primary" href="{{ $printUrl }}" target="_blank" rel="noopener noreferrer">{{ $primaryActionLabel ?? 'Abrir impresion' }}</a>
            <a class="btn btn-secondary" href="{{ $redirectUrl }}">{{ $secondaryActionLabel ?? 'Volver al pedido' }}</a>
        </div>
    </div>

    <script>
        window.addEventListener('load', function () {
            window.open(@json($printUrl), '_blank', 'noopener,noreferrer');
            setTimeout(function () {
                window.location.href = @json($redirectUrl);
            }, 1200);
        });
    </script>
</body>
</html>
