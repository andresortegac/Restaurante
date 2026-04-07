<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Acceso Denegado - Error 403</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <!-- Custom CSS para error 403 -->
    <link rel="stylesheet" href="{{ asset('css/pages/403.css') }}">
</head>
<body>
    <div class="error-container">
        <div class="error-content">
            <div class="error-icon">
                <i class="fas fa-ban"></i>
            </div>
            <div class="error-code">403</div>
            <h1 class="error-title">Acceso Denegado</h1>
            <p class="error-message">
                No tienes permiso para acceder a este recurso. 
                Por favor, verifica tu rol o permisos con un administrador del sistema.
            </p>

            <div class="error-details">
                <p><strong>Usuario:</strong> {{ Auth::user()->name ?? 'Annimo' }}</p>
                <p><strong>Email:</strong> {{ Auth::user()->email ?? 'N/A' }}</p>
            </div>

            <div class="error-actions">
                <a href="{{ route('dashboard') }}" class="btn-error btn-primary-error">
                    <i class="fas fa-arrow-left"></i> Volver al Dashboard
                </a>
                <button onclick="window.history.back()" class="btn-error btn-secondary-error">
                    <i class="fas fa-undo"></i> Atrs
                </button>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

    <script>
        // Mostrar alerta de acceso denegado
        window.addEventListener('load', function() {
            Swal.fire({
                icon: 'error',
                title: 'Acceso Denegado (403)',
                text: 'No tienes permisos para acceder a este recurso.',
                confirmButtonColor: '#667eea',
                confirmButtonText: 'Entendido',
                allowOutsideClick: false
            });
        });
    </script>
</body>
</html>
