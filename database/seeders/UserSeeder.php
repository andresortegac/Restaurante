<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::query()
            ->whereIn('email', [
                'cajero@restaurante.com',
                'mesero@restaurante.com',
            ])
            ->delete();

        $users = [
            [
                'name' => 'Admin',
                'email' => 'admin@restaurante.com',
                'password' => Hash::make('password123'),
                'role' => 'Admin',
            ],
        ];

        foreach ($users as $userData) {
            $role = $userData['role'];
            unset($userData['role']);

            $user = User::firstOrCreate(['email' => $userData['email']], $userData);

            // Asignar rol al usuario
            $roleModel = Role::where('name', $role)->first();
            if ($roleModel) {
                $user->roles()->syncWithoutDetaching([$roleModel->id]);
            }
        }
    }
}
