<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>@yield('title', 'RestaurantePOS - Sistema de Gestión')</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --sidebar-bg: #2c3e50;
            --sidebar-hover: #34495e;
            --text-light: #ecf0f1;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: #333;
        }

        /* Navbar */
        .navbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 1rem 2rem;
        }

        .navbar-brand {
            font-size: 24px;
            font-weight: bold;
            color: white !important;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .navbar-toggler {
            border-color: rgba(255, 255, 255, 0.5);
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.55%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.85) !important;
            transition: color 0.3s;
        }

        .nav-link:hover {
            color: white !important;
        }

        .user-dropdown {
            color: white;
        }

        .user-dropdown:hover {
            color: #e9ecef;
        }

        /* Sidebar */
        .sidebar {
            background-color: var(--sidebar-bg);
            color: var(--text-light);
            min-height: calc(100vh - 70px);
            padding: 2rem 0;
            position: fixed;
            width: 250px;
            top: 70px;
            left: 0;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar-title {
            padding: 0 1.5rem 1rem;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            color: rgba(236, 240, 241, 0.6);
            letter-spacing: 1px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
        }

        .sidebar-menu li {
            margin: 0;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: var(--text-light);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .sidebar-menu a:hover {
            background-color: var(--sidebar-hover);
            border-left-color: var(--primary-color);
            padding-left: 1.8rem;
        }

        .sidebar-menu a.active {
            background-color: var(--sidebar-hover);
            border-left-color: var(--primary-color);
            color: var(--primary-color);
        }

        .sidebar-menu i {
            width: 20px;
            margin-right: 15px;
            text-align: center;
        }

        .sidebar-submenu {
            list-style: none;
            padding-left: 1.5rem;
            display: none;
        }

        .sidebar-submenu.show {
            display: block;
        }

        .sidebar-submenu a {
            padding: 0.5rem 1.5rem;
            font-size: 14px;
        }

        /* Main content */
        .main-content {
            margin-left: 250px;
            padding: 2rem 2rem 2rem 2rem;
            min-height: calc(100vh - 70px);
        }

        .card {
            border: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border-radius: 10px;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            filter: brightness(1.1);
        }

        .welcome-section {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }

        .welcome-section h1 {
            color: var(--primary-color);
            margin-bottom: 10px;
            font-size: 28px;
        }

        .welcome-section p {
            color: #666;
            font-size: 16px;
            line-height: 1.6;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            border-left: 4px solid var(--primary-color);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .stat-card h3 {
            color: #999;
            font-size: 14px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
            color: var(--primary-color);
        }

        .badge-role {
            background-color: #fff3cd;
            color: #856404;
            padding: 8px 12px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            margin-right: 10px;
            margin-bottom: 10px;
        }

        .badge-permission {
            background-color: #d1ecf1;
            color: #0c5460;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 11px;
            display: inline-block;
            margin-right: 5px;
            margin-bottom: 5px;
        }

        /* Logout button */
        .logout-btn {
            background-color: rgba(255, 255, 255, 0.2);
            border: 1px solid white;
            color: white;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
            text-decoration: none;
        }

        .logout-btn:hover {
            background-color: rgba(255, 255, 255, 0.3);
            color: white;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                top: 0;
                min-height: auto;
                max-height: 0;
                overflow: hidden;
                transition: max-height 0.3s;
            }

            .sidebar.show {
                max-height: 500px;
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    @yield('styles')
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="{{ route('dashboard') }}">
                <i class="fas fa-utensils"></i> RestaurantePOS
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle user-dropdown" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> {{ Auth::user()->name }}
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="#"><i class="fas fa-user"></i> Mi Perfil</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-cog"></i> Configuración</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="#" onclick="cerrarSesion(event)">
                                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="d-flex">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-title">Menú Principal</div>
            <ul class="sidebar-menu">
                <li>
                    <a href="{{ route('dashboard') }}" class="@if(request()->routeIs('dashboard')) active @endif">
                        <i class="fas fa-chart-line"></i> Dashboard
                    </a>
                </li>

                @if(Auth::user()->hasPermission('orders.view'))
                <li>
                    <a href="#ordersMenu" data-bs-toggle="collapse">
                        <i class="fas fa-clipboard-list"></i> Pedidos
                        <i class="fas fa-chevron-down ms-auto" style="width: auto;"></i>
                    </a>
                    <ul class="sidebar-submenu collapse" id="ordersMenu">
                        @if(Auth::user()->hasPermission('orders.view'))
                        <li><a href="#"><i class="fas fa-list"></i> Ver Pedidos</a></li>
                        @endif
                        @if(Auth::user()->hasPermission('orders.create'))
                        <li><a href="#"><i class="fas fa-plus"></i> Nuevo Pedido</a></li>
                        @endif
                    </ul>
                </li>
                @endif

                @if(Auth::user()->hasPermission('tables.view'))
                <li>
                    <a href="#">
                        <i class="fas fa-chair"></i> Mesas
                    </a>
                </li>
                @endif

                @if(Auth::user()->hasPermission('inventory.view'))
                <li>
                    <a href="#inventoryMenu" data-bs-toggle="collapse">
                        <i class="fas fa-boxes"></i> Inventario
                        <i class="fas fa-chevron-down ms-auto" style="width: auto;"></i>
                    </a>
                    <ul class="sidebar-submenu collapse" id="inventoryMenu">
                        @if(Auth::user()->hasPermission('inventory.view'))
                        <li><a href="#"><i class="fas fa-list"></i> Ver Productos</a></li>
                        @endif
                        @if(Auth::user()->hasPermission('inventory.create'))
                        <li><a href="#"><i class="fas fa-plus"></i> Agregar Producto</a></li>
                        @endif
                    </ul>
                </li>
                @endif

                @if(Auth::user()->hasPermission('customers.view'))
                <li>
                    <a href="#">
                        <i class="fas fa-users"></i> Clientes
                    </a>
                </li>
                @endif

                @if(Auth::user()->hasPermission('reports.view'))
                <li>
                    <a href="#">
                        <i class="fas fa-chart-bar"></i> Reportes
                    </a>
                </li>
                @endif

                @if(Auth::user()->hasRole('Admin'))
                <li>
                    <a href="#adminMenu" data-bs-toggle="collapse">
                        <i class="fas fa-cog"></i> Administración
                        <i class="fas fa-chevron-down ms-auto" style="width: auto;"></i>
                    </a>
                    <ul class="sidebar-submenu collapse" id="adminMenu">
                        @if(Auth::user()->hasPermission('users.view'))
                        <li><a href="#"><i class="fas fa-users"></i> Usuarios</a></li>
                        @endif
                        @if(Auth::user()->hasPermission('roles.view'))
                        <li><a href="#"><i class="fas fa-shield-alt"></i> Roles y Permisos</a></li>
                        @endif
                        @if(Auth::user()->hasPermission('settings.view'))
                        <li><a href="#"><i class="fas fa-sliders-h"></i> Configuración</a></li>
                        @endif
                    </ul>
                </li>
                @endif
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content flex-grow-1">
            @yield('content')
        </main>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

    @include('components.alerts')

    <script>
        // Cerrar sesión con confirmación
        function cerrarSesion(event) {
            event.preventDefault();
            
            Swal.fire({
                title: '¿Cerrar sesión?',
                text: '¿Estás seguro de que deseas cerrar tu sesión?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#667eea',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, cerrar',
                cancelButtonText: 'Cancelar',
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed) {
                    // Crear formulario dinámico para POST
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '{{ route("logout") }}';
                    
                    const token = document.createElement('input');
                    token.type = 'hidden';
                    token.name = '_token';
                    token.value = '{{ csrf_token() }}';
                    
                    form.appendChild(token);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        // Manejar submenús en dispositivos móviles
        document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.classList.toggle('show');
                }
            });
        });
    </script>

    @yield('scripts')
</body>
</html>
