<?php

/**
 * EJEMPLOS DE IMPLEMENTACIÓN DEL SISTEMA DE AUTENTICACIÓN
 * Y AUTORIZACIÓN CON ROLES Y PERMISOS
 */

// ============================================================================
// EJEMPLO 1: Proteger rutas con middlewares
// ============================================================================

// En routes/web.php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;

Route::middleware(['auth'])->group(function () {
    
    // Solo accesible para usuarios con rol Admin
    Route::get('/admin/dashboard', function () {
        return view('admin.dashboard');
    })->middleware('role:Admin');
    
    // Múltiples roles (usuario debe tener al menos uno)
    Route::get('/panel', function () {
        return view('panel');
    })->middleware('role:Admin,Cajero,Gerente');
    
    // Usando permisos
    Route::resource('orders', OrderController::class)->middleware('permission:orders.view');
    
    // Rutas solo para crear/editar
    Route::post('/orders', [OrderController::class, 'store'])
        ->middleware('permission:orders.create');
    
    Route::put('/orders/{id}', [OrderController::class, 'update'])
        ->middleware('permission:orders.edit');
    
    Route::delete('/orders/{id}', [OrderController::class, 'destroy'])
        ->middleware('permission:orders.delete');
    
});

// ============================================================================
// EJEMPLO 2: Verificación en Controladores
// ============================================================================

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\Order;

class OrderController extends Controller
{
    /**
     * Listar pedidos
     * Verifica permisos en el controlador también
     */
    public function index()
    {
        // Verificar que el usuario está autenticado
        if (!Auth::check()) {
            return redirect('/login');
        }
        
        $user = Auth::user();
        
        // Verificar permiso
        if (!$user->hasPermission('orders.view')) {
            return response()->view('errors.403', [], 403);
        }
        
        // Obtener pedidos según el rol
        if ($user->hasRole('Admin')) {
            $orders = Order::all();
        } else if ($user->hasRole('Cajero')) {
            $orders = Order::latest()->get();
        } else if ($user->hasRole('Mesero')) {
            // Mesero solo ve sus propios pedidos
            $orders = Order::where('user_id', $user->id)->get();
        }
        
        return view('orders.index', compact('orders'));
    }
    
    /**
     * Crear nuevo pedido
     */
    public function store()
    {
        $user = Auth::user();
        
        // Verificación de permisos
        if (!$user->hasPermission('orders.create')) {
            abort(403, 'No tienes permisos para crear pedidos');
        }
        
        // Crear el pedido
        $order = Order::create([
            'user_id' => $user->id,
            'table_number' => request('table_number'),
            'items' => request('items'),
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Pedido creado exitosamente',
            'order' => $order
        ]);
    }
    
    /**
     * Editar pedido
     */
    public function update($id)
    {
        $user = Auth::user();
        
        // Verificar permiso
        if (!$user->hasPermission('orders.edit')) {
            abort(403, 'No tienes permisos para editar pedidos');
        }
        
        $order = Order::findOrFail($id);
        
        // Solo administrador puede editar cualquier pedido
        if (!$user->hasRole('Admin') && $order->user_id !== $user->id) {
            abort(403, 'No puedes editar este pedido');
        }
        
        $order->update(request()->only(['status', 'items']));
        
        return response()->json([
            'success' => true,
            'message' => 'Pedido actualizado exitosamente',
            'order' => $order
        ]);
    }
}

// ============================================================================
// EJEMPLO 3: Controlador de Administración de Roles y Permisos
// ============================================================================

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class RoleController extends Controller
{
    /**
     * Listar roles (solo para Admin)
     */
    public function index()
    {
        Auth::user()->authorizeWithPermission('roles.view');
        
        $roles = Role::with('permissions')->paginate(15);
        return view('roles.index', compact('roles'));
    }
    
    /**
     * Crear nuevo rol
     */
    public function store()
    {
        Auth::user()->authorizeWithPermission('roles.create');
        
        $validated = request()->validate([
            'name' => 'required|string|unique:roles',
            'description' => 'nullable|string',
        ]);
        
        $role = Role::create($validated);
        
        // Si se enviaron permisos, asignarlos
        if (request()->has('permissions')) {
            $role->permissions()->sync(request('permissions'));
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Rol creado exitosamente',
            'role' => $role->load('permissions')
        ]);
    }
    
    /**
     * Editar rol
     */
    public function update($id)
    {
        Auth::user()->authorizeWithPermission('roles.edit');
        
        $role = Role::findOrFail($id);
        
        $validated = request()->validate([
            'name' => 'required|string|unique:roles,name,' . $role->id,
            'description' => 'nullable|string',
        ]);
        
        $role->update($validated);
        
        // Actualizar permisos
        if (request()->has('permissions')) {
            $role->permissions()->sync(request('permissions'));
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Rol actualizado exitosamente',
            'role' => $role->load('permissions')
        ]);
    }
    
    /**
     * Asignar rol a usuario
     */
    public function assignToUser()
    {
        Auth::user()->authorizeWithPermission('roles.edit');
        
        $user = User::findOrFail(request('user_id'));
        $role = Role::findOrFail(request('role_id'));
        
        // Usar sync para reemplazar todos los roles
        // O attach para agregar sin reemplazar
        $user->roles()->attach($role);
        
        return response()->json([
            'success' => true,
            'message' => "Rol '{$role->name}' asignado a {$user->name}"
        ]);
    }
    
    /**
     * Remover rol de usuario
     */
    public function removeFromUser()
    {
        Auth::user()->authorizeWithPermission('roles.edit');
        
        $user = User::findOrFail(request('user_id'));
        $role = Role::findOrFail(request('role_id'));
        
        $user->roles()->detach($role);
        
        return response()->json([
            'success' => true,
            'message' => "Rol '{$role->name}' removido de {$user->name}"
        ]);
    }
}

// ============================================================================
// EJEMPLO 4: Métodos auxiliares que podrías agregar al modelo User
// ============================================================================

namespace App\Models;

class User extends Authenticatable
{
    // ... código existente ...
    
    /**
     * Método auxiliar para verificar autorización y lanzar excepción
     */
    public function authorizeWithPermission(string $permission)
    {
        if (!$this->hasPermission($permission)) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'No tienes permiso para esta acción'
            );
        }
        return true;
    }
    
    /**
     * Método auxiliar para verificar autorización con rol
     */
    public function authorizeWithRole(string $role)
    {
        if (!$this->hasRole($role)) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'Tu rol no tiene acceso a esta acción'
            );
        }
        return true;
    }
    
    /**
     * Obtener todos los permisos del usuario (sin duplicados)
     */
    public function getAllPermissions()
    {
        return $this->roles()
            ->with('permissions')
            ->get()
            ->flatMap(function ($role) {
                return $role->permissions;
            })
            ->unique('id')
            ->values();
    }
}

// ============================================================================
// EJEMPLO 5: En Vistas Blade
// ============================================================================

<!-- Mostrar algo solo si está autenticado -->
@auth
    <p>Hola, {{ Auth::user()->name }}!</p>
@else
    <a href="/login">Inicia sesión</a>
@endauth

<!-- Verificar rol (requiere servir los datos desde el controlador) -->
@if(Auth::user()->hasRole('Admin'))
    <a href="/admin/settings">Configuración</a>
@endif

<!-- Verificar permiso -->
@if(Auth::user()->hasPermission('orders.create'))
    <button onclick="createOrder()">Crear Nuevo Pedido</button>
@endif

<!-- Mostrar elemento solo para múltiples roles -->
@if(Auth::user()->hasRole(['Admin', 'Gerente']))
    <section>
        <h2>Reportes Analíticos</h2>
        <!-- Contenido -->
    </section>
@endif

<!-- Mostrar tabla de acciones según permisos -->
<table>
    <thead>
        <tr>
            <th>Pedido</th>
            <th>Cliente</th>
            <th>Estado</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        @foreach($orders as $order)
            <tr>
                <td>{{ $order->id }}</td>
                <td>{{ $order->customer_name }}</td>
                <td>{{ $order->status }}</td>
                <td>
                    @if(Auth::user()->hasPermission('orders.edit'))
                        <a href="/orders/{{ $order->id }}/edit">Editar</a>
                    @endif
                    
                    @if(Auth::user()->hasPermission('orders.delete'))
                        <form method="POST" action="/orders/{{ $order->id }}" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button>Eliminar</button>
                        </form>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

// ============================================================================
// EJEMPLO 6: Llamadas API desde JavaScript
// ============================================================================

// Verificar información del usuario
fetch('/api/user')
    .then(response => response.json())
    .then(data => {
        console.log('Usuario:', data.user);
        console.log('Roles:', data.roles);
        console.log('Permisos:', data.permissions);
    });

// Verificar si tiene un rol
fetch('/api/user/has-role/Admin')
    .then(response => response.json())
    .then(data => {
        if (data.has_role) {
            console.log('Usuario es Admin');
        }
    });

// Verificar si tiene permiso
fetch('/api/user/has-permission/orders.create')
    .then(response => response.json())
    .then(data => {
        if (data.has_permission) {
            console.log('Puede crear pedidos');
        } else {
            console.log('No puede crear pedidos');
        }
    });

// ============================================================================
// EJEMPLO 7: Seeders para agregar datos
// ============================================================================

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class GerenciadorRoleSeeder extends Seeder
{
    public function run(): void
    {
        // Crear rol Gerente
        $role = Role::create([
            'name' => 'Gerente',
            'description' => 'Gerente del restaurante'
        ]);
        
        // Obtener permisos relevantes
        $permissions = Permission::whereIn('name', [
            'dashboard.view',
            'orders.view',
            'orders.edit',
            'reports.view',
            'reports.export',
            'customers.view',
            'inventory.view',
        ])->get();
        
        // Asignar permisos
        $role->permissions()->sync($permissions->pluck('id'));
    }
}

// ============================================================================
// EJEMPLO 8: Políticas de Autorización (Gates)
// ============================================================================

// En app/Providers/AuthServiceProvider.php

use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    // Define una política usando Gate
    Gate::define('delete-order', function (User $user, Order $order) {
        // Solo Admin puede eliminar
        if ($user->hasRole('Admin')) {
            return true;
        }
        
        // El dueño del pedido puede eliminarlo si tiene permiso
        return $user->id === $order->user_id && 
               $user->hasPermission('orders.delete');
    });
    
    // Usar en controlador
    // $this->authorize('delete-order', $order);
}

// En controlador:
public function destroy(Order $order)
{
    $this->authorize('delete-order', $order);
    
    $order->delete();
    
    return response()->json(['success' => true]);
}
