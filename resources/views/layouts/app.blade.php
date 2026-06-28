<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{ str_replace('RestaurantePOS', config('app.name', 'Solomo & Pomo'), trim($__env->yieldContent('title', config('app.name', 'Solomo & Pomo') . ' - Sistema de Gestión'))) }}</title>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    
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
    @stack('styles')
</head>
<body>
    @php
        $currentUser = Auth::user()?->loadMissing('roles.permissions');
    @endphp

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="{{ route('dashboard') }}">
                <i class="fas fa-utensils"></i> {{ config('app.name', 'Solomo & Pomo') }}
            </a>
            <button class="navbar-toggler" type="button" id="sidebarToggle">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link user-dropdown dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> {{ $currentUser->name }}
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
                $isPosSalesHistoryRoute = request()->routeIs('pos.sales-history.*');
                $isBillingRoute = request()->routeIs('billing.*');
                $isBillingHistoryRoute = request()->routeIs('billing.history') || $isPosSalesHistoryRoute;
                $isBillingVoidedRoute = request()->routeIs('billing.voided');
                $isProductsRoute = request()->routeIs('products.*');
                $isProductsCategoriesRoute = request()->routeIs('products.categories.*');
                $isTablesRoute = request()->routeIs('tables.*');
                $isTablesCatalogRoute = request()->routeIs('tables.index') || request()->routeIs('tables.show') || request()->routeIs('tables.edit');
                $isTableHistoryRoute = request()->routeIs('tables.history.*');
                $isCustomersRoute = request()->routeIs('customers.*');
                $isElectronicInvoicesRoute = request()->routeIs('electronic-invoices.*');
                $isOrdersHistoryRoute = request()->routeIs('orders.history.*');
                $isOrdersRoute = request()->routeIs('orders.*') && ! $isOrdersHistoryRoute;
                $isCashRoute = request()->routeIs('cash-management.*');
                $isReportsRoute = request()->routeIs('reports.*');
                $isAdminUsersRoute = request()->routeIs('admin.users.*');
                $isAdminRolesRoute = request()->routeIs('admin.roles.*');
                $isAdminPermissionsRoute = request()->routeIs('admin.permissions.*');
                $isAdminAccessRoute = $isAdminRolesRoute || $isAdminPermissionsRoute;
                $isOrdersMenuExpanded = $isOrdersRoute || $isOrdersHistoryRoute;
                $isBillingMenuExpanded = $isBillingRoute || $isElectronicInvoicesRoute || $isPosSalesHistoryRoute;
            @endphp
            
            <ul class="sidebar-menu">
                <li>
                    <a href="{{ route('dashboard') }}" class="{{ $isDashboardRoute ? 'active' : '' }}">
                        <i class="fas fa-dashboard"></i> Dashboard
                    </a>
                </li>

                @if(Auth::user()->hasRole('Admin') || Auth::user()->hasAnyPermission(['products.view', 'products.create', 'products.edit', 'products.delete']))
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
                        @if(Auth::user()->hasRole('Admin') || Auth::user()->hasAnyPermission(['products.view', 'products.create', 'products.edit', 'products.delete']))
                        <li>
                            <a href="{{ route('products.categories.index') }}" class="{{ $isProductsCategoriesRoute ? 'active' : '' }}">
                                <i class="fas fa-tags"></i> Categorias
                            </a>
                        </li>
                        @endif
                    </ul>
                </li>
                @endif

                @if(Auth::user()->hasRole('Admin'))
                <li>
                    <a href="#" data-toggle-menu class="{{ $isAdminUsersRoute ? 'expanded' : '' }}">
                        <i class="fas fa-users"></i> Usuarios
                        <span class="toggle-icon float-end"><i class="fas fa-chevron-right"></i></span>
                    </a>
                    <ul class="sidebar-submenu {{ $isAdminUsersRoute ? 'show' : '' }}">
                        <li><a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.index') || request()->routeIs('admin.users.edit') ? 'active' : '' }}"><i class="fas fa-list"></i> Listar</a></li>
                        <li><a href="{{ route('admin.users.create') }}" class="{{ request()->routeIs('admin.users.create') ? 'active' : '' }}"><i class="fas fa-plus"></i> Crear</a></li>
                    </ul>
                </li>
                @endif

                @if(Auth::user()->hasRole('Admin'))
                <li>
                    <a href="#" data-toggle-menu class="{{ $isAdminAccessRoute ? 'expanded' : '' }}">
                        <i class="fas fa-shield-alt"></i> Roles y Permisos
                        <span class="toggle-icon float-end"><i class="fas fa-chevron-right"></i></span>
                    </a>
                    <ul class="sidebar-submenu {{ $isAdminAccessRoute ? 'show' : '' }}">
                        <li><a href="{{ route('admin.roles.index') }}" class="{{ $isAdminRolesRoute ? 'active' : '' }}"><i class="fas fa-lock"></i> Roles</a></li>
                        <li><a href="{{ route('admin.permissions.index') }}" class="{{ $isAdminPermissionsRoute ? 'active' : '' }}"><i class="fas fa-key"></i> Permisos</a></li>
                    </ul>
                </li>
                @endif

                @if(Auth::user()->hasRole('Admin') || Auth::user()->hasRole('Cajero') || Auth::user()->hasAnyPermission(['boxes.view', 'boxes.open', 'boxes.close', 'boxes.movements', 'boxes.reports']))
                <li>
                    <a href="#" data-toggle-menu class="{{ $isCashRoute ? 'expanded' : '' }}">
                        <i class="fas fa-cash-register"></i> Gestion de Caja
                        <span class="toggle-icon float-end"><i class="fas fa-chevron-right"></i></span>
                    </a>
                    <ul class="sidebar-submenu {{ $isCashRoute ? 'show' : '' }}">
                        <li><a href="{{ route('cash-management.index') }}" class="{{ request()->routeIs('cash-management.index') || request()->routeIs('cash-management.show') || request()->routeIs('cash-management.edit') ? 'active' : '' }}"><i class="fas fa-wallet"></i> Cajas</a></li>
                        <li><a href="{{ route('cash-management.history') }}" class="{{ request()->routeIs('cash-management.history') ? 'active' : '' }}"><i class="fas fa-clock-rotate-left"></i> Historial</a></li>
                    </ul>
                </li>
                @endif

                @if(Auth::user()->hasRole('Admin') || Auth::user()->hasAnyPermission(['orders.view', 'orders.create', 'orders.edit']))
                <li>
                    <a href="#" data-toggle-menu class="{{ $isOrdersMenuExpanded ? 'expanded' : '' }}">
                        <i class="fas fa-receipt"></i> Pedidos
                        <span class="toggle-icon float-end"><i class="fas fa-chevron-right"></i></span>
                    </a>
                    <ul class="sidebar-submenu {{ $isOrdersMenuExpanded ? 'show' : '' }}">
                        @if(Auth::user()->hasRole('Admin') || Auth::user()->hasAnyPermission(['orders.view', 'orders.create']))
                        <li><a href="{{ route('orders.index') }}" class="{{ $isOrdersRoute ? 'active' : '' }}"><i class="fas fa-clipboard-list"></i> Pedidos por mesa</a></li>
                        @endif
                        @if(Auth::user()->hasRole('Admin') || Auth::user()->hasAnyPermission(['orders.view', 'orders.edit']))
                        <li><a href="{{ route('orders.index') }}#active-orders"><i class="fas fa-fire-burner"></i> Activos y cocina</a></li>
                        @endif
                        @if(Auth::user()->hasRole('Admin') || Auth::user()->hasPermission('orders.view'))
                        <li><a href="{{ route('orders.history.index') }}" class="{{ $isOrdersHistoryRoute ? 'active' : '' }}"><i class="fas fa-clock-rotate-left"></i> Historial de pedidos</a></li>
                        @endif
                    </ul>
                </li>
                @endif

                @if(Auth::user()->hasRole('Admin') || Auth::user()->hasRole('Cajero') || Auth::user()->hasAnyPermission(['billing.view', 'billing.charge', 'billing.history', 'electronic_invoices.view', 'electronic_invoices.manage', 'electronic_invoices.retry']))
                <li>
                    <a href="#" data-toggle-menu class="{{ $isBillingMenuExpanded ? 'expanded' : '' }}">
                        <i class="fas fa-file-invoice-dollar"></i> Facturación
                        <span class="toggle-icon float-end"><i class="fas fa-chevron-right"></i></span>
                    </a>
                    <ul class="sidebar-submenu {{ $isBillingMenuExpanded ? 'show' : '' }}">
                        @if(Auth::user()->hasRole('Admin') || Auth::user()->hasAnyPermission(['billing.view', 'billing.charge']))
                        <li><a href="{{ route('billing.index') }}" class="{{ request()->routeIs('billing.index') || request()->routeIs('billing.checkout') ? 'active' : '' }}"><i class="fas fa-cash-register"></i> Cuentas por cobrar</a></li>
                        <li><a href="{{ route('billing.manual') }}" class="{{ request()->routeIs('billing.manual') ? 'active' : '' }}"><i class="fas fa-keyboard"></i> Cobro manual</a></li>
                        @endif
                        @if(Auth::user()->hasRole('Admin') || Auth::user()->hasAnyPermission(['billing.view', 'billing.history']))
                        <li><a href="{{ route('billing.history') }}" class="{{ $isBillingHistoryRoute ? 'active' : '' }}"><i class="fas fa-receipt"></i> Ventas generales</a></li>
                        @endif
                        @if(Auth::user()->hasRole('Admin'))
                        <li><a href="{{ route('billing.voided') }}" class="{{ $isBillingVoidedRoute ? 'active' : '' }}"><i class="fas fa-ban"></i> Facturas anuladas</a></li>
                        @endif
                        @if(Auth::user()->hasRole('Admin') || Auth::user()->hasAnyPermission(['electronic_invoices.view', 'electronic_invoices.manage']))
                        <li><a href="{{ route('electronic-invoices.index') }}" class="{{ request()->routeIs('electronic-invoices.index') || request()->routeIs('electronic-invoices.show') ? 'active' : '' }}"><i class="fas fa-list"></i> Facturas electrónicas</a></li>
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
                        <li><a href="{{ route('tables.index') }}" class="{{ $isTablesCatalogRoute ? 'active' : '' }}"><i class="fas fa-list"></i> Ver mesas</a></li>
                        <li><a href="{{ route('tables.history.index') }}" class="{{ $isTableHistoryRoute ? 'active' : '' }}"><i class="fas fa-clock-rotate-left"></i> Historial por mesa</a></li>
                        @endif
                    </ul>
                </li>
                @endif

                @if(Auth::user()->hasAnyPermission(['reports.view']))
                <li>
                    <a href="#" data-toggle-menu class="{{ $isReportsRoute ? 'expanded' : '' }}">
                        <i class="fas fa-chart-bar"></i> Reportes
                        <span class="toggle-icon float-end"><i class="fas fa-chevron-right"></i></span>
                    </a>
                    <ul class="sidebar-submenu {{ $isReportsRoute ? 'show' : '' }}">
                        <li><a href="{{ route('reports.index') }}" class="{{ request()->routeIs('reports.index') ? 'active' : '' }}"><i class="fas fa-money-bill-wave"></i> Ventas</a></li>
                        <li><a href="{{ route('reports.analytics') }}" class="{{ request()->routeIs('reports.analytics') ? 'active' : '' }}"><i class="fas fa-chart-pie"></i> Analisis</a></li>
                    </ul>
                </li>
                @endif

                @if(Auth::user()->hasAnyPermission(['customers.view']))
                <li>
                    <a href="{{ route('customers.index') }}" class="{{ $isCustomersRoute ? 'active' : '' }}">
                        <i class="fas fa-users"></i> Clientes
                    </a>
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

    @yield('scripts')
    @stack('scripts')

    <!-- Alertas del sistema -->
    @include('components.alerts')
</body>
</html>
