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
    
    <!-- Custom CSS para layout -->
    <link rel="stylesheet" href="{{ asset('css/layouts/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/layouts/navbar.css') }}">
    <link rel="stylesheet" href="{{ asset('css/layouts/sidebar.css') }}">
    <link rel="stylesheet" href="{{ asset('css/dashboard/dashboard.css') }}">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="{{ route('dashboard') }}">
                <i class="fas fa-utensils"></i> RestaurantePOS
            </a>
            <button class="navbar-toggler" type="button" id="sidebarToggle">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link user-dropdown dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> {{ Auth::user()->name }}
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="{{ route('dashboard') }}"><i class="fas fa-home"></i> Dashboard</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="page-layout">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <h6 class="sidebar-title"><i class="fas fa-bars"></i> Men Principal</h6>
            <ul class="sidebar-menu">
                <li>
                    <a href="{{ route('dashboard') }}" class="active">
                        <i class="fas fa-dashboard"></i> Dashboard
                    </a>
                </li>

                @if(Auth::user()->hasAnyPermission(['users.view', 'users.create', 'users.edit', 'users.delete']))
                <li>
                    <a href="#" data-toggle-menu>
                        <i class="fas fa-users"></i> Usuarios
                        <span class="toggle-icon float-end"><i class="fas fa-chevron-right"></i></span>
                    </a>
                    <ul class="sidebar-submenu">
                        @if(Auth::user()->hasPermission('users.view'))
                        <li><a href="#"><i class="fas fa-list"></i> Listar</a></li>
                        @endif
                        @if(Auth::user()->hasPermission('users.create'))
                        <li><a href="#"><i class="fas fa-plus"></i> Crear</a></li>
                        @endif
                    </ul>
                </li>
                @endif

                @if(Auth::user()->hasAnyPermission(['roles.view', 'permissions.view']))
                <li>
                    <a href="#" data-toggle-menu>
                        <i class="fas fa-shield-alt"></i> Roles y Permisos
                        <span class="toggle-icon float-end"><i class="fas fa-chevron-right"></i></span>
                    </a>
                    <ul class="sidebar-submenu">
                        @if(Auth::user()->hasPermission('roles.view'))
                        <li><a href="#"><i class="fas fa-lock"></i> Roles</a></li>
                        @endif
                        @if(Auth::user()->hasPermission('permissions.view'))
                        <li><a href="#"><i class="fas fa-key"></i> Permisos</a></li>
                        @endif
                    </ul>
                </li>
                @endif

                @if(Auth::user()->hasAnyPermission(['orders.view', 'orders.create']))
                <li>
                    <a href="#" data-toggle-menu>
                        <i class="fas fa-receipt"></i> Pedidos
                        <span class="toggle-icon float-end"><i class="fas fa-chevron-right"></i></span>
                    </a>
                    <ul class="sidebar-submenu">
                        @if(Auth::user()->hasPermission('orders.view'))
                        <li><a href="#"><i class="fas fa-list"></i> Ver Pedidos</a></li>
                        @endif
                        @if(Auth::user()->hasPermission('orders.create'))
                        <li><a href="#"><i class="fas fa-plus"></i> Nuevo Pedido</a></li>
                        @endif
                    </ul>
                </li>
                @endif

                @if(Auth::user()->hasAnyPermission(['tables.view', 'tables.manage']))
                <li>
                    <a href="#" data-toggle-menu>
                        <i class="fas fa-chair"></i> Mesas
                        <span class="toggle-icon float-end"><i class="fas fa-chevron-right"></i></span>
                    </a>
                    <ul class="sidebar-submenu">
                        @if(Auth::user()->hasPermission('tables.view'))
                        <li><a href="#"><i class="fas fa-list"></i> Ver Mesas</a></li>
                        @endif
                        @if(Auth::user()->hasPermission('tables.manage'))
                        <li><a href="#"><i class="fas fa-edit"></i> Gestionar</a></li>
                        @endif
                    </ul>
                </li>
                @endif

                @if(Auth::user()->hasAnyPermission(['inventory.view', 'inventory.manage']))
                <li>
                    <a href="#" data-toggle-menu>
                        <i class="fas fa-box"></i> Inventario
                        <span class="toggle-icon float-end"><i class="fas fa-chevron-right"></i></span>
                    </a>
                    <ul class="sidebar-submenu">
                        @if(Auth::user()->hasPermission('inventory.view'))
                        <li><a href="#"><i class="fas fa-list"></i> Ver Stock</a></li>
                        @endif
                        @if(Auth::user()->hasPermission('inventory.manage'))
                        <li><a href="#"><i class="fas fa-edit"></i> Gestionar</a></li>
                        @endif
                    </ul>
                </li>
                @endif

                @if(Auth::user()->hasAnyPermission(['reports.view']))
                <li>
                    <a href="#" data-toggle-menu>
                        <i class="fas fa-chart-bar"></i> Reportes
                        <span class="toggle-icon float-end"><i class="fas fa-chevron-right"></i></span>
                    </a>
                    <ul class="sidebar-submenu">
                        <li><a href="#"><i class="fas fa-money-bill-wave"></i> Ventas</a></li>
                        <li><a href="#"><i class="fas fa-chart-pie"></i> Anlisis</a></li>
                    </ul>
                </li>
                @endif

                @if(Auth::user()->hasAnyPermission(['customers.view']))
                <li>
                    <a href="#" data-toggle-menu>
                        <i class="fas fa-users"></i> Clientes
                        <span class="toggle-icon float-end"><i class="fas fa-chevron-right"></i></span>
                    </a>
                    <ul class="sidebar-submenu">
                        <li><a href="#"><i class="fas fa-list"></i> Listar</a></li>
                        <li><a href="#"><i class="fas fa-plus"></i> Nuevo</a></li>
                    </ul>
                </li>
                @endif
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            @yield('content')
        </main>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    
    <!-- Custom JS para layout -->
    <script src="{{ asset('js/layouts/sidebar.js') }}"></script>

    <!-- Alertas del sistema -->
    @include('components.alerts')
</body>
</html>
