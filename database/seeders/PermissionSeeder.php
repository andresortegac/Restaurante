<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // Usuarios
            ['name' => 'users.view', 'description' => 'Ver usuarios'],
            ['name' => 'users.create', 'description' => 'Crear usuarios'],
            ['name' => 'users.edit', 'description' => 'Editar usuarios'],
            ['name' => 'users.delete', 'description' => 'Eliminar usuarios'],

            // Roles
            ['name' => 'roles.view', 'description' => 'Ver roles'],
            ['name' => 'roles.create', 'description' => 'Crear roles'],
            ['name' => 'roles.edit', 'description' => 'Editar roles'],
            ['name' => 'roles.delete', 'description' => 'Eliminar roles'],

            // Pedidos
            ['name' => 'orders.view', 'description' => 'Ver pedidos'],
            ['name' => 'orders.create', 'description' => 'Crear pedidos'],
            ['name' => 'orders.edit', 'description' => 'Editar pedidos'],
            ['name' => 'orders.delete', 'description' => 'Eliminar pedidos'],

            // Mesas
            ['name' => 'tables.view', 'description' => 'Ver mesas'],
            ['name' => 'tables.create', 'description' => 'Crear mesas'],
            ['name' => 'tables.edit', 'description' => 'Editar mesas'],
            ['name' => 'tables.delete', 'description' => 'Eliminar mesas'],

            // Inventario
            ['name' => 'inventory.view', 'description' => 'Ver inventario'],
            ['name' => 'inventory.create', 'description' => 'Crear productos de inventario'],
            ['name' => 'inventory.edit', 'description' => 'Editar productos de inventario'],
            ['name' => 'inventory.delete', 'description' => 'Eliminar productos de inventario'],

            // Reportes
            ['name' => 'reports.view', 'description' => 'Ver reportes'],
            ['name' => 'reports.export', 'description' => 'Exportar reportes'],

            // Dashboard
            ['name' => 'dashboard.view', 'description' => 'Ver dashboard'],

            // Clientes
            ['name' => 'customers.view', 'description' => 'Ver clientes'],
            ['name' => 'customers.create', 'description' => 'Crear clientes'],
            ['name' => 'customers.edit', 'description' => 'Editar clientes'],
            ['name' => 'customers.delete', 'description' => 'Eliminar clientes'],

            // Configuraci�n
            ['name' => 'settings.view', 'description' => 'Ver configuraci�n'],
            ['name' => 'settings.edit', 'description' => 'Editar configuraci�n'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission['name']], $permission);
        }
    }
}
