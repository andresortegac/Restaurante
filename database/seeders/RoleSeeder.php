<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['name' => 'Admin', 'description' => 'Administrador del sistema'],
            ['name' => 'Cajero', 'description' => 'Responsable de caja y pagos'],
            ['name' => 'Mesero', 'description' => 'Personal de servicio al cliente'],
            ['name' => 'Cocina', 'description' => 'Personal de cocina'],
            ['name' => 'Cliente', 'description' => 'Cliente del restaurante'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role['name']], $role);
        }

        // Asignar permisos a roles
        $this->assignPermissionsToRoles();
    }

    private function assignPermissionsToRoles(): void
    {
        // Admin tiene todos los permisos
        $adminRole = Role::where('name', 'Admin')->first();
        if ($adminRole) {
            $adminRole->permissions()->sync(
                Permission::pluck('id')->toArray()
            );
        }

        // Cajero
        $cashierRole = Role::where('name', 'Cajero')->first();
        if ($cashierRole) {
            $cashierPermissions = Permission::whereIn('name', [
                'dashboard.view',
                'orders.view',
                'orders.create',
                'customers.view',
                'customers.create',
                'reservations.view',
                'reservations.create',
                'reservations.edit',
                'reservations.delete',
                'reports.view',
            ])->pluck('id')->toArray();
            $cashierRole->permissions()->sync($cashierPermissions);
        }

        // Mesero
        $waiterRole = Role::where('name', 'Mesero')->first();
        if ($waiterRole) {
            $waiterPermissions = Permission::whereIn('name', [
                'dashboard.view',
                'orders.view',
                'orders.create',
                'orders.edit',
                'customers.view',
                'reservations.view',
                'reservations.create',
                'reservations.edit',
                'reservations.delete',
            ])->pluck('id')->toArray();
            $waiterRole->permissions()->sync($waiterPermissions);
        }

        // Cocina
        $kitchenRole = Role::where('name', 'Cocina')->first();
        if ($kitchenRole) {
            $kitchenPermissions = Permission::whereIn('name', [
                'orders.view',
                'orders.edit',
                'inventory.view',
            ])->pluck('id')->toArray();
            $kitchenRole->permissions()->sync($kitchenPermissions);
        }

        // Cliente
        $clientRole = Role::where('name', 'Cliente')->first();
        if ($clientRole) {
            $clientPermissions = Permission::whereIn('name', [
                'orders.view',
                'customers.view',
            ])->pluck('id')->toArray();
            $clientRole->permissions()->sync($clientPermissions);
        }
    }
}
