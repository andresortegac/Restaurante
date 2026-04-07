# Sistema de Autenticación, Roles y Permisos

## Descripción General

Este documento describe la implementación del sistema de autenticación, roles y permisos para el Sistema de Gestión de Restaurantes.

## Estructura de Base de Datos

### Tablas Principales

1. **users** - Tabla de usuarios del sistema
   - id
   - name (nombre del usuario)
   - email (correo único)
   - password (contraseña hasheada)
   - email_verified_at
   - remember_token
   - timestamps

2. **roles** - Tabla de roles disponibles
   - id
   - name (nombre único del rol)
   - description (descripción del rol)
   - timestamps

3. **permissions** - Tabla de permisos disponibles
   - id
   - name (nombre único del permiso)
   - description (descripción del permiso)
   - timestamps

4. **user_role** - Tabla pivote (relación many-to-many entre usuarios y roles)
   - id
   - user_id (FK a users)
   - role_id (FK a roles)
   - timestamps

5. **role_permission** - Tabla pivote (relación many-to-many entre roles y permisos)
   - id
   - role_id (FK a roles)
   - permission_id (FK a permissions)
   - timestamps

## Roles Predefinidos

1. **Admin** - Administrador del sistema
   - Tiene acceso a TODOS los permisos

2. **Cajero** - Responsable de caja y pagos
   - Ver dashboard
   - Ver, crear pedidos
   - Ver y crear clientes
   - Ver reportes

3. **Mesero** - Personal de servicio al cliente
   - Ver dashboard
   - Ver, crear y editar pedidos
   - Ver mesas
   - Ver clientes

4. **Cocina** - Personal de cocina
   - Ver pedidos
   - Editar pedidos
   - Ver inventario

5. **Cliente** - Cliente del restaurante
   - Ver pedidos propios
   - Ver información de clientes

## Permisos Disponibles

### Usuarios
- `users.view` - Ver usuarios
- `users.create` - Crear usuarios
- `users.edit` - Editar usuarios
- `users.delete` - Eliminar usuarios

### Roles
- `roles.view` - Ver roles
- `roles.create` - Crear roles
- `roles.edit` - Editar roles
- `roles.delete` - Eliminar roles

### Pedidos
- `orders.view` - Ver pedidos
- `orders.create` - Crear pedidos
- `orders.edit` - Editar pedidos
- `orders.delete` - Eliminar pedidos

### Mesas
- `tables.view` - Ver mesas
- `tables.create` - Crear mesas
- `tables.edit` - Editar mesas
- `tables.delete` - Eliminar mesas

### Inventario
- `inventory.view` - Ver inventario
- `inventory.create` - Crear productos de inventario
- `inventory.edit` - Editar productos de inventario
- `inventory.delete` - Eliminar productos de inventario

### Reportes
- `reports.view` - Ver reportes
- `reports.export` - Exportar reportes

### Dashboard
- `dashboard.view` - Ver dashboard

### Clientes
- `customers.view` - Ver clientes
- `customers.create` - Crear clientes
- `customers.edit` - Editar clientes
- `customers.delete` - Eliminar clientes

### Configuración
- `settings.view` - Ver configuración
- `settings.edit` - Editar configuración

## Usuarios de Prueba

Después de ejecutar `php artisan migrate:fresh --seed`, los siguientes usuarios están disponibles:

| Email | Password | Rol |
|-------|----------|-----|
| admin@restaurante.com | password123 | Admin |
| cajero@restaurante.com | password123 | Cajero |
| mesero@restaurante.com | password123 | Mesero |
| cocina@restaurante.com | password123 | Cocina |

## Modelos y Métodos

### Modelo User

```php
// Verificar si el usuario tiene un rol específico
$user->hasRole('Admin'); // retorna true/false
$user->hasRole(['Admin', 'Cajero']); // retorna true si tiene cualquiera de los roles

// Verificar si el usuario tiene un permiso específico
$user->hasPermission('orders.create'); // retorna true/false

// Verificar si el usuario tiene todos los permisos
$user->hasAllPermissions(['orders.view', 'orders.create']); // retorna true/false

// Verificar si el usuario tiene al menos uno de los permisos
$user->hasAnyPermission(['orders.delete', 'users.delete']); // retorna true/false

// Obtener roles del usuario
$user->roles; // retorna Collection de Roles

// Obtener permisos del usuario (a través de roles)
$user->roles()->with('permissions')->get();
```

### Modelo Role

```php
// Verificar si el rol tiene un permiso específico
$role->hasPermission('orders.create'); // retorna true/false

// Obtener usuarios con este rol
$role->users; // retorna Collection de Users

// Obtener permisos del rol
$role->permissions; // retorna Collection de Permissions
```

### Modelo Permission

```php
// Obtener roles que tienen este permiso
$permission->roles; // retorna Collection de Roles
```

## Rutas Principales

### Autenticación:

- `GET /login` - Mostrar formulario de login
- `POST /login` - Procesar login
- `POST /logout` - Cerrar sesión

### Dashboard y API:

- `GET /dashboard` - Mostrar dashboard (requiere autenticación)
- `GET /api/user` - Obtener información del usuario (JSON)
- `GET /api/user/has-role/{role}` - Verificar si tiene rol (JSON)
- `GET /api/user/has-permission/{permission}` - Verificar si tiene permiso (JSON)

## Uso de Middlewares

### Middleware de Rol

Proteger una ruta verificando que el usuario tiene un rol específico:

```php
Route::get('/admin', function () {
    // ...
})->middleware('role:Admin');
```

Verificar múltiples roles (usuario debe tener al menos uno):

```php
Route::get('/panel', function () {
    // ...
})->middleware('role:Admin,Cajero');
```

### Middleware de Permiso

Proteger una ruta verificando que el usuario tiene un permiso específico:

```php
Route::get('/orders', function () {
    // ...
})->middleware('permission:orders.view');
```

Verificar múltiples permisos (usuario debe tener al menos uno):

```php
Route::post('/orders', function () {
    // ...
})->middleware('permission:orders.create,orders.edit');
```

## Uso en Controladores

### Verificar Autenticación

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function index()
    {
        // Obtener el usuario autenticado
        $user = Auth::user();
        
        // Verificar si está autenticado
        if (!Auth::check()) {
            return redirect('/login');
        }
        
        // Verificar rol
        if ($user->hasRole('Admin')) {
            // Solo para administradores
        }
        
        // Verificar permiso
        if ($user->hasPermission('orders.view')) {
            // Mostrar pedidos
        }
    }
}
```

## Uso en Vistas (Blade)

```blade
<!-- Verificar si el usuario está autenticado -->
@auth
    <div>Estás autenticado: {{ Auth::user()->name }}</div>
@endauth

@guest
    <a href="/login">Iniciar sesión</a>
@endguest

<!-- Verificar rol (requiere Helper) -->
@if(Auth::user()->hasRole('Admin'))
    <div>Solo visible para administradores</div>
@endif

<!-- Verificar permiso -->
@if(Auth::user()->hasPermission('orders.create'))
    <button>Crear Pedido</button>
@endif
```

## Asignar Roles a Usuarios (Programáticamente)

```php
use App\Models\User;
use App\Models\Role;

// Obtener usuario y rol
$user = User::find(1);
$role = Role::where('name', 'Cajero')->first();

// Asignar rol
$user->roles()->attach($role);

// O con sincronización (reemplazar roles)
$user->roles()->sync([$role->id]);

// Desasignar rol
$user->roles()->detach($role);
```

## Asignar Permisos a Roles (Programáticamente)

```php
use App\Models\Role;
use App\Models\Permission;

// Obtener rol y permisos
$role = Role::where('name', 'Cajero')->first();
$permissions = Permission::whereIn('name', [
    'orders.view',
    'orders.create'
])->get();

// Asignar permisos
$role->permissions()->attach($permissions);

// O con sincronización
$role->permissions()->sync($permissions->pluck('id'));
```

## Flujo de Autenticación

1. Usuario accede a `/login`
2. El controlador `AuthController` muestra el formulario
3. Usuario envía credentials (email, password)
4. `AuthController@login` valida las credenciales
5. Si son válidas:
   - Se crea una sesión
   - Se redirige a `/dashboard`
6. Si no son válidas:
   - Se retorna a `/login` con error

## Flujo de Autorización (Roles)

1. Usuario intenta acceder a una ruta protegida con middleware `role:Admin`
2. Middleware `CheckRole` verifica si `Auth::user()->hasRole('Admin')` retorna true
3. Si es true: continúa con la solicitud
4. Si es false: retorna error 403

## Flujo de Autorización (Permisos)

1. Usuario intenta acceder a una ruta protegida con middleware `permission:orders.create`
2. Middleware `CheckPermission` verifica si `Auth::user()->hasPermission('orders.create')` retorna true
3. Si es true: continúa con la solicitud
4. Si es false: retorna error 403

## Registrar Nuevos Middlewares

Los middlewares se deben registrar en `bootstrap/app.php` (Laravel 11) o `app/Http/Kernel.php` (Laravel 10):

```php
// En bootstrap/app.php (Laravel 11):
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'role' => \App\Http\Middleware\CheckRole::class,
        'permission' => \App\Http\Middleware\CheckPermission::class,
    ]);
})

// En app/Http/Kernel.php (Laravel 10):
protected $routeMiddleware = [
    'role' => \App\Http\Middleware\CheckRole::class,
    'permission' => \App\Http\Middleware\CheckPermission::class,
];
```

## Mantenimiento y Escalabilidad

### Agregar Nuevo Rol

```php
use App\Models\Role;

// Crear un nuevo rol
$role = Role::create([
    'name' => 'Gerente',
    'description' => 'Gerente del restaurante'
]);

// Asignar permisos específicos
$role->permissions()->attach([/* IDs de permisos */]);
```

### Agregar Nuevo Permiso

```php
use App\Models\Permission;

// Crear un nuevo permiso
$permission = Permission::create([
    'name' => 'reports.create',
    'description' => 'Crear reportes personalizados'
]);
```

### Modificar Roles y Permisos Existentes

```php
// Actualizar un rol
$role = Role::find(1);
$role->update(['description' => 'Nueva descripción']);

// Actualizar permisos de un rol
$role->permissions()->sync([1, 2, 3]);
```

## Precauciones de Seguridad

1. **Siempre usar middlewares** para proteger rutas sensibles
2. **Validar permisos en controladores** además de middlewares
3. **No confiar en datos del cliente** para autorización
4. **Usar métodos del modelo** como `hasPermission()` en lugar de verificar roles directamente
5. **Auditar cambios** en roles y permisos
6. **Cambiar contraseñas** de prueba en producción

## Próximos Pasos

1. Registrar middlewares en `bootstrap/app.php`
2. Crear rutas protegidas con roles/permisos
3. Implementar auditoría de cambios
4. Crear interfaz de administración de roles/permisos
5. Agregar validación de dos factores
6. Implementar bloqueo de cuenta por intentos fallidos

