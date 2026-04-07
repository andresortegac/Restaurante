<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Login - Sistema de Gestión de Restaurante</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <!-- Custom CSS para login -->
    <link rel="stylesheet" href="{{ asset('css/auth/login.css') }}">
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>RestaurantePOS</h1>
            <p>Sistema de Gestión para Restaurantes</p>
        </div>

        <form method="POST" action="{{ route('login') }}" id="loginForm" accept-charset="utf-8">
            @csrf

            <div class="form-group">
                <label for="email">Correo Electrónico</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    placeholder="tu@email.com"
                    class="@error('email') is-invalid @enderror"
                />
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    placeholder="????????"
                    class="@error('password') is-invalid @enderror"
                />
            </div>

            <div class="remember-me">
                <input
                    type="checkbox"
                    id="remember"
                    name="remember"
                    value="true"
                />
                <label for="remember">Recuérdame</label>
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
            </button>
        </form>

        <div class="demo-credentials">
            <h4><i class="fas fa-info-circle"></i> Credenciales de Demostración:</h4>
            <ul>
                <li><strong>Admin:</strong> admin@restaurante.com / password123</li>
                <li><strong>Cajero:</strong> cajero@restaurante.com / password123</li>
                <li><strong>Mesero:</strong> mesero@restaurante.com / password123</li>
                <li><strong>Cocina:</strong> cocina@restaurante.com / password123</li>
            </ul>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

    <!-- Script para pasar errores al JS -->
    <script>
        @if($errors->any())
            window.loginErrors = {!! json_encode($errors->all()) !!};
        @endif
        
        @if(session('success'))
            window.successMessage = '{{ session('success') }}';
        @endif
    </script>

    <!-- Custom JS para login -->
    <script src="{{ asset('js/auth/login.js') }}"></script>
</body>
</html>
