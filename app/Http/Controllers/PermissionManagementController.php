<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Support\Str;

class PermissionManagementController extends Controller
{
    public function index()
    {
        $permissions = Permission::query()
            ->with('roles')
            ->orderBy('name')
            ->get();

        return view('admin.permissions.index', [
            'permissions' => $permissions,
            'summary' => [
                'permissions' => $permissions->count(),
                'groups' => $permissions->groupBy(fn (Permission $permission) => Str::before($permission->name, '.'))->count(),
                'assigned' => $permissions->filter(fn (Permission $permission) => $permission->roles->isNotEmpty())->count(),
            ],
        ]);
    }
}
