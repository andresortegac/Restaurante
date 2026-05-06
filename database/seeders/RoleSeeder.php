<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $allowedRoles = ['Admin', 'Cajero', 'Mesero'];

        $roles = [
            ['name' => 'Admin', 'description' => 'Administrador del sistema'],
            ['name' => 'Cajero', 'description' => 'Responsable de caja y pagos'],
            ['name' => 'Mesero', 'description' => 'Personal de servicio al cliente'],
        ];

        $rolesToRemove = Role::query()
            ->whereNotIn('name', $allowedRoles)
            ->pluck('id');

        if ($rolesToRemove->isNotEmpty()) {
            \DB::table('user_role')->whereIn('role_id', $rolesToRemove)->delete();
            \DB::table('role_permission')->whereIn('role_id', $rolesToRemove)->delete();
            Role::query()->whereIn('id', $rolesToRemove)->delete();
        }

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role['name']], $role);
        }

        $this->assignPermissionsToRoles();
    }

    private function assignPermissionsToRoles(): void
    {
        $adminRole = Role::where('name', 'Admin')->first();
        if ($adminRole) {
            $adminRole->permissions()->sync(
                Permission::pluck('id')->toArray()
            );
        }

        $cashierRole = Role::where('name', 'Cajero')->first();
        if ($cashierRole) {
            $cashierPermissions = Permission::whereIn('name', [
                'dashboard.view',
                'boxes.view',
                'boxes.open',
                'boxes.close',
                'boxes.movements',
                'boxes.reports',
                'orders.view',
                'orders.create',
                'customers.view',
                'customers.create',
                'deliveries.view',
                'deliveries.create',
                'deliveries.edit',
                'delivery_drivers.view',
                'delivery_drivers.create',
                'delivery_drivers.edit',
                'electronic_invoices.view',
                'electronic_invoices.manage',
                'reservations.view',
                'reservations.create',
                'reservations.edit',
                'reservations.delete',
                'reports.view',
            ])->pluck('id')->toArray();
            $cashierRole->permissions()->sync($cashierPermissions);
        }

        $waiterRole = Role::where('name', 'Mesero')->first();
        if ($waiterRole) {
            $waiterPermissions = Permission::whereIn('name', [
                'dashboard.view',
                'orders.view',
                'orders.create',
                'orders.edit',
                'customers.view',
                'deliveries.view',
                'deliveries.edit',
                'delivery_drivers.view',
                'electronic_invoices.view',
                'reservations.view',
                'reservations.create',
                'reservations.edit',
                'reservations.delete',
            ])->pluck('id')->toArray();
            $waiterRole->permissions()->sync($waiterPermissions);
        }
    }
}
