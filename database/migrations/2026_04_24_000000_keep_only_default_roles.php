<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $allowedRoles = ['Admin', 'Cajero', 'Mesero'];

        $roleIdsToRemove = DB::table('roles')
            ->whereNotIn('name', $allowedRoles)
            ->pluck('id');

        if ($roleIdsToRemove->isEmpty()) {
            return;
        }

        DB::table('user_role')->whereIn('role_id', $roleIdsToRemove)->delete();
        DB::table('role_permission')->whereIn('role_id', $roleIdsToRemove)->delete();
        DB::table('roles')->whereIn('id', $roleIdsToRemove)->delete();
    }

    public function down(): void
    {
        //
    }
};
