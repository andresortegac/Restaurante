<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
    <link rel="stylesheet" href="{{ asset('css/products/management.css') }}">
    <link rel="stylesheet" href="{{ asset('css/tables/management.css') }}">
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
            @php
                $isDashboardRoute = request()->routeIs('dashboard');
                $isPosRoute = request()->routeIs('pos.*');
                $isProductsRoute = request()->routeIs('products.*');
                $isTablesRoute = request()->routeIs('tables.*');
                $isOrdersRoute = request()->routeIs('orders.*');
            @endphp
            
            <ul class="sidebar-menu">
                <li>
                    <a href="{{ route('dashboard') }}" class="{{ $isDashboardRoute ? 'active' : '' }}">
                        <i class="fas fa-dashboard"></i> Dashboard
                    </a>
                </li>

                <li>
                    <a href="#" data-toggle-menu class="{{ $isPosRoute ? 'expanded' : '' }}">
                        <i class="fas fa-cash-register"></i> Punto de Venta
                        <span class="toggle-icon"><i class="fas fa-chevron-right"></i></span>
                    </a>
                    <ul class="sidebar-submenu {{ $isPosRoute ? 'show' : '' }}">
                        <li>
                            <a href="{{ route('pos.index') }}" class="{{ request()->routeIs('pos.index') ? 'active' : '' }}">
                                <i class="fas fa-store"></i> Abrir POS
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('pos.sales-history.index') }}" class="{{ request()->routeIs('pos.sales-history.*') ? 'active' : '' }}">
                                <i class="fas fa-clock-rotate-left"></i> Historial de ventas
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('pos.promo-codes.create') }}" class="{{ request()->routeIs('pos.promo-codes.*') ? 'active' : '' }}">
                                <i class="fas fa-ticket-alt"></i> Codigos promocionales
                            </a>
                        </li>
                    </ul>
                </li>

                @if(Auth::user()->hasRole('Admin') || Auth::user()->hasAnyPermission(['products.view', 'products.create', 'products.edit', 'products.delete', 'combos.view', 'combos.create', 'combos.edit', 'combos.delete', 'taxes.view', 'taxes.create', 'taxes.edit', 'taxes.delete']))
                <li>
                    <a href="#" data-toggle-menu class="{{ $isProductsRoute ? 'expanded' : '' }}">
                        <i class="fas fa-utensils"></i> Gestion de Productos
                        <span class="toggle-icon"><i class="fas fa-chevron-right"></i></span>
                    </a>
                    <ul class="sidebar-submenu {{ $isProductsRoute ? 'show' : '' }}">
                        @if(Auth::user()->hasRole('Admin') || Auth::user()->hasAnyPermission(['products.view', 'products.create', 'products.edit', 'products.delete']))
                        <li>
                            <a href="{{ route('products.menu.index') }}" class="{{ request()->routeIs('products.menu.*') ? 'active' : '' }}">
                                <i class="fas fa-book-open"></i> Menu y Precios
                            </a>
                        </li>
                        @endif
                        @if(Auth::user()->hasRole('Admin') || Auth::user()->hasAnyPermission(['combos.view', 'combos.create', 'combos.edit', 'combos.delete']))
                        <li>
                            <a href="{{ route('products.combos.index') }}" class="{{ request()->routeIs('products.combos.*') ? 'active' : '' }}">
                                <i class="fas fa-layer-group"></i> Combos
                            </a>
                        </li>
                        @endif
                        @if(Auth::user()->hasRole('Admin') || Auth::user()->hasAnyPermission(['taxes.view', 'taxes.create', 'taxes.edit', 'taxes.delete']))
                        <li>
                            <a href="{{ route('products.taxes.index') }}" class="{{ request()->routeIs('products.taxes.*') ? 'active' : '' }}">
                                <i class="fas fa-percent"></i> Impuestos
                            </a>
                        </li>
                        @endif
                    </ul>
                </li>
                @endif

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

                @if(Auth::user()->hasRole('Admin') || Auth::user()->hasAnyPermission(['orders.view', 'orders.create', 'orders.edit']))
                <li>
                    <a href="#" data-toggle-menu class="{{ $isOrdersRoute ? 'expanded' : '' }}">
                        <i class="fas fa-receipt"></i> Pedidos
                        <span class="toggle-icon float-end"><i class="fas fa-chevron-right"></i></span>
                    </a>
                    <ul class="sidebar-submenu {{ $isOrdersRoute ? 'show' : '' }}">
                        @if(Auth::user()->hasRole('Admin') || Auth::user()->hasAnyPermission(['orders.view', 'orders.create']))
                        <li><a href="{{ route('orders.index') }}" class="{{ request()->routeIs('orders.*') ? 'active' : '' }}"><i class="fas fa-clipboard-list"></i> Pedidos por mesa</a></li>
                        @endif
                        @if(Auth::user()->hasRole('Admin') || Auth::user()->hasAnyPermission(['orders.view', 'orders.edit']))
                        <li><a href="{{ route('orders.index') }}#active-orders"><i class="fas fa-fire-burner"></i> Activos y cocina</a></li>
                        @endif
                    </ul>
                </li>
                @endif

                @if(Auth::user()->hasRole('Admin') || Auth::user()->hasAnyPermission(['tables.view', 'tables.create', 'tables.edit', 'tables.delete']))
                <li>
                    <a href="#" data-toggle-menu class="{{ $isTablesRoute ? 'expanded' : '' }}">
                        <i class="fas fa-chair"></i> Mesas
                        <span class="toggle-icon float-end"><i class="fas fa-chevron-right"></i></span>
                    </a>
                    <ul class="sidebar-submenu {{ $isTablesRoute ? 'show' : '' }}">
                        @if(Auth::user()->hasRole('Admin') || Auth::user()->hasPermission('tables.view'))
                        <li><a href="{{ route('tables.index') }}" class="{{ request()->routeIs('tables.index') || request()->routeIs('tables.show') || request()->routeIs('tables.edit') ? 'active' : '' }}"><i class="fas fa-list"></i> Ver mesas</a></li>
                        @endif
                        @if(Auth::user()->hasRole('Admin') || Auth::user()->hasPermission('tables.create'))
                        <li><a href="{{ route('tables.create') }}" class="{{ request()->routeIs('tables.create') ? 'active' : '' }}"><i class="fas fa-plus"></i> Nueva mesa</a></li>
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

