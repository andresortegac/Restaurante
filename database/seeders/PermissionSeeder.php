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

            // Caja
            ['name' => 'boxes.view', 'description' => 'Ver gestion de caja'],
            ['name' => 'boxes.open', 'description' => 'Abrir cajas'],
            ['name' => 'boxes.close', 'description' => 'Cerrar cajas'],
            ['name' => 'boxes.movements', 'description' => 'Registrar ingresos y egresos manuales de caja'],
            ['name' => 'boxes.reports', 'description' => 'Ver historial y cierres mensuales de caja'],

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

            // Productos
            ['name' => 'products.view', 'description' => 'Ver menu y productos'],
            ['name' => 'products.create', 'description' => 'Crear productos del menu'],
            ['name' => 'products.edit', 'description' => 'Editar productos del menu'],
            ['name' => 'products.delete', 'description' => 'Eliminar productos del menu'],

            // Combos
            ['name' => 'combos.view', 'description' => 'Ver combos o productos compuestos'],
            ['name' => 'combos.create', 'description' => 'Crear combos o productos compuestos'],
            ['name' => 'combos.edit', 'description' => 'Editar combos o productos compuestos'],
            ['name' => 'combos.delete', 'description' => 'Eliminar combos o productos compuestos'],

            // Impuestos
            ['name' => 'taxes.view', 'description' => 'Ver configuracion de impuestos'],
            ['name' => 'taxes.create', 'description' => 'Crear configuraciones de impuestos'],
            ['name' => 'taxes.edit', 'description' => 'Editar configuraciones de impuestos'],
            ['name' => 'taxes.delete', 'description' => 'Eliminar configuraciones de impuestos'],

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

            // Domicilios
            ['name' => 'deliveries.view', 'description' => 'Ver domicilios'],
            ['name' => 'deliveries.create', 'description' => 'Crear domicilios'],
            ['name' => 'deliveries.edit', 'description' => 'Editar domicilios'],
            ['name' => 'deliveries.delete', 'description' => 'Eliminar domicilios'],

            // Reservas
            ['name' => 'reservations.view', 'description' => 'Ver reservas'],
            ['name' => 'reservations.create', 'description' => 'Crear reservas'],
            ['name' => 'reservations.edit', 'description' => 'Editar reservas'],
            ['name' => 'reservations.delete', 'description' => 'Eliminar reservas'],

            // Configuracion
            ['name' => 'settings.view', 'description' => 'Ver configuracion'],
            ['name' => 'settings.edit', 'description' => 'Editar configuracion'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission['name']], $permission);
        }
    }
}
