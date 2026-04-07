@extends('layouts.app')

@section('title', 'Dashboard - RestaurantePOS')

@section('content')
    <!-- Welcome Section -->
    <div class="welcome-section">
        <h1><i class="fas fa-wave-hand"></i> ¡Bienvenido, {{ Auth::user()->name }}!</h1>
        <p>
            Has iniciado sesión correctamente en el Sistema de Gestión de Restaurantes.
            Aquí puedes gestionar todas las operaciones del restaurante.
        </p>
    </div>

    <!-- Dashboard Stats -->
    <div class="dashboard-grid">
        <div class="stat-card">
            <h3><i class="fas fa-clipboard-list"></i> Pedidos Pendientes</h3>
            <div class="value">0</div>
        </div>
        <div class="stat-card">
            <h3><i class="fas fa-chair"></i> Mesas Ocupadas</h3>
            <div class="value">0</div>
        </div>
        <div class="stat-card">
            <h3><i class="fas fa-dollar-sign"></i> Ventas Hoy</h3>
            <div class="value">$0.00</div>
        </div>
        <div class="stat-card">
            <h3><i class="fas fa-users"></i> Clientes</h3>
            <div class="value">0</div>
        </div>
    </div>

    <!-- User Information -->
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-user-circle"></i> Información de Cuenta</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td><strong>Nombre:</strong></td>
                            <td>{{ Auth::user()->name }}</td>
                        </tr>
                        <tr>
                            <td><strong>Email:</strong></td>
                            <td>{{ Auth::user()->email }}</td>
                        </tr>
                        <tr>
                            <td><strong>Último acceso:</strong></td>
                            <td>Hace poco</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-shield-alt"></i> Roles y Permisos</h5>
                </div>
                <div class="card-body">
                    <div id="roles-container" style="margin-bottom: 15px;">
                        <p class="text-muted"><i class="fas fa-spinner fa-spin"></i> Cargando roles...</p>
                    </div>
                    <small class="text-muted d-block">Tienes <strong id="permissions-count">0</strong> permisos activos</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Permissions -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-lock"></i> Permisos Autorizados</h5>
        </div>
        <div class="card-body">
            <div id="permissions-container">
                <p class="text-muted"><i class="fas fa-spinner fa-spin"></i> Cargando permisos...</p>
            </div>
        </div>
    </div>

    <!-- Active Sessions -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-history"></i> Estado del Sistema</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <p class="text-center">
                        <strong>Sesión Activa</strong><br>
                        <span class="badge bg-success">En línea</span>
                    </p>
                </div>
                <div class="col-md-3">
                    <p class="text-center">
                        <strong>Base de Datos</strong><br>
                        <span class="badge bg-success">Conectada</span>
                    </p>
                </div>
                <div class="col-md-3">
                    <p class="text-center">
                        <strong>Servidor</strong><br>
                        <span class="badge bg-success">Operativo</span>
                    </p>
                </div>
                <div class="col-md-3">
                    <p class="text-center">
                        <strong>Versión</strong><br>
                        <span class="badge bg-info">v1.0.0</span>
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        // Cargar información del usuario desde la API
        fetch('{{ route("api.user") }}')
            .then(response => response.json())
            .then(data => {
                // Mostrar roles
                const rolesContainer = document.getElementById('roles-container');
                if (data.roles && data.roles.length > 0) {
                    rolesContainer.innerHTML = data.roles
                        .map(role => `<span class="badge-role">${role}</span>`)
                        .join('');
                } else {
                    rolesContainer.innerHTML = '<p class="text-muted">Sin roles asignados</p>';
                }

                // Mostrar contador de permisos
                document.getElementById('permissions-count').textContent = data.permissions.length;

                // Mostrar permisos
                const permissionsContainer = document.getElementById('permissions-container');
                if (data.permissions && data.permissions.length > 0) {
                    permissionsContainer.innerHTML = data.permissions
                        .map(perm => `<span class="badge-permission">${perm}</span>`)
                        .join('');
                } else {
                    permissionsContainer.innerHTML = '<p class="text-muted">Sin permisos asignados</p>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('roles-container').innerHTML = 
                    '<p class="text-danger">Error al cargar información</p>';
            });
    </script>
@endsection
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: #333;
        }

        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .navbar-brand {
            font-size: 24px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .navbar-user {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info {
            text-align: right;
        }

        .user-info p {
            font-size: 14px;
            margin: 2px 0;
        }

        .user-name {
            font-weight: 600;
            font-size: 15px;
        }

        .user-role {
            font-size: 12px;
            opacity: 0.9;
        }

        .logout-btn {
            background-color: rgba(255, 255, 255, 0.2);
            border: 1px solid white;
            color: white;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }

        .logout-btn:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .welcome-section {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .welcome-section h1 {
            color: #667eea;
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
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .card {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }

        .card h3 {
            color: #667eea;
            margin-bottom: 1rem;
            font-size: 18px;
        }

        .card p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }

        .permisos-section {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .permisos-section h2 {
            color: #667eea;
            margin-bottom: 1.5rem;
            font-size: 22px;
        }

        .permissions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .permission-badge {
            background-color: #e7f3ff;
            border: 1px solid #b3d9ff;
            color: #004085;
            padding: 8px 12px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 500;
            text-align: center;
        }

        .role-badge {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 8px 12px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
        }

        .info-section {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin: 2rem 0;
        }

        .info-section h2 {
            color: #667eea;
            margin-bottom: 1.5rem;
            font-size: 22px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table tr {
            border-bottom: 1px solid #eee;
        }

        .info-table th {
            background-color: #f5f7fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #667eea;
        }

        .info-table td {
            padding: 12px;
        }

        .badge-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .navbar-user {
                width: 100%;
                justify-content: center;
            }

            .container {
                padding: 1rem;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="navbar-brand">
            ??? RestaurantePOS
        </div>
        <div class="navbar-user">
            <div class="user-info">
                <div class="user-name">{{ Auth::user()->name }}</div>
                <div class="user-role" id="user-role-display">Cargando...</div>
            </div>
            <form method="POST" action="{{ route('logout') }}" style="margin: 0;">
                @csrf
                <button type="submit" class="logout-btn">Cerrar Sesión</button>
            </form>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="container">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h1>Bienvenido, {{ Auth::user()->name }}! ??</h1>
            <p>
                Has iniciado sesión correctamente en el Sistema de Gestión de Restaurantes.
                Aquí puedes gestionar las operaciones del restaurante, incluyendo pedidos, mesas, inventario y más.
            </p>
        </div>

        <!-- Feature Cards -->
        <div class="dashboard-grid">
            <div class="card">
                <h3>?? Gestión de Pedidos</h3>
                <p>Visualiza, crea y edita pedidos. Autoriza cambios según tu rol.</p>
            </div>
            <div class="card">
                <h3>?? Gestión de Mesas</h3>
                <p>Controla el estado de las mesas y asigna pedidos a cada una.</p>
            </div>
            <div class="card">
                <h3>?? Inventario</h3>
                <p>Mantén el control del stock de ingredientes y productos.</p>
            </div>
            <div class="card">
                <h3>?? Clientes</h3>
                <p>Gestiona información de clientes y promociones.</p>
            </div>
            <div class="card">
                <h3>?? Reportes</h3>
                <p>Visualiza reportes de ventas y operaciones del restaurante.</p>
            </div>
            <div class="card">
                <h3>?? Configuración</h3>
                <p>Accede a configuraciones del sistema según permisos.</p>
            </div>
        </div>

        <!-- User Information Section -->
        <div class="info-section">
            <h2>Información de Cuenta</h2>
            <table class="info-table">
                <tr>
                    <th>Propiedad</th>
                    <th>Valor</th>
                </tr>
                <tr>
                    <td><strong>Nombre:</strong></td>
                    <td>{{ Auth::user()->name }}</td>
                </tr>
                <tr>
                    <td><strong>Email:</strong></td>
                    <td>{{ Auth::user()->email }}</td>
                </tr>
                <tr>
                    <td><strong>Roles:</strong></td>
                    <td id="user-roles-display"><span class="badge-success">Cargando...</span></td>
                </tr>
                <tr>
                    <td><strong>Permisos Totales:</strong></td>
                    <td id="user-permissions-count">0</td>
                </tr>
            </table>
        </div>

        <!-- Roles and Permissions Section -->
        <div class="info-section">
            <h2>Roles Asignados</h2>
            <div class="permissions-grid" id="roles-container">
                <p style="grid-column: 1/-1; color: #999;">Cargando roles...</p>
            </div>
        </div>

        <div class="info-section">
            <h2>Permisos Autorizados</h2>
            <div class="permissions-grid" id="permissions-container">
                <p style="grid-column: 1/-1; color: #999;">Cargando permisos...</p>
            </div>
        </div>
    </div>

    <script>
        // Cargar información del usuario
        fetch('{{ route("api.user") }}')
            .then(response => response.json())
            .then(data => {
                // Actualizar información del rol
                const rolesDisplay = data.roles.join(', ') || 'Sin rol asignado';
                document.getElementById('user-role-display').textContent = rolesDisplay;
                document.getElementById('user-roles-display').innerHTML = data.roles
                    .map(role => `<span class="role-badge">${role}</span>`)
                    .join(' ') || '<span style="color: #999;">Sin rol asignado</span>';

                // Actualizar permisos
                document.getElementById('user-permissions-count').textContent = data.permissions.length;
                const permissionsContainer = document.getElementById('permissions-container');
                if (data.permissions.length > 0) {
                    permissionsContainer.innerHTML = data.permissions
                        .map(perm => `<div class="permission-badge">${perm}</div>`)
                        .join('');
                } else {
                    permissionsContainer.innerHTML = '<p style="grid-column: 1/-1; color: #999;">Sin permisos asignados</p>';
                }

                // Actualizar roles
                const rolesContainer = document.getElementById('roles-container');
                if (data.roles.length > 0) {
                    rolesContainer.innerHTML = data.roles
                        .map(role => `<div class="role-badge">${role}</div>`)
                        .join('');
                } else {
                    rolesContainer.innerHTML = '<p style="grid-column: 1/-1; color: #999;">Sin roles asignados</p>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('user-role-display').textContent = 'Error al cargar';
            });
    </script>
</body>
</html>
