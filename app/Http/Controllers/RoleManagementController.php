<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleManagementController extends Controller
{
    private const ALLOWED_ROLE_NAMES = ['Admin', 'Cajero', 'Mesero'];

    public function index()
    {
        $roles = Role::query()
            ->withCount(['users', 'permissions'])
            ->whereIn('name', self::ALLOWED_ROLE_NAMES)
            ->orderBy('name')
            ->get();

        return view('admin.roles.index', [
            'roles' => $roles,
            'summary' => [
                'roles' => $roles->count(),
                'permissions' => Permission::count(),
                'admins' => $roles->firstWhere('name', 'Admin')?->users_count ?? 0,
            ],
        ]);
    }

    public function create()
    {
        return redirect()
            ->route('admin.roles.index')
            ->with('info', 'Solo se permiten los roles base Admin, Cajero y Mesero.');
    }

    public function store(Request $request): RedirectResponse
    {
        return redirect()
            ->route('admin.roles.index')
            ->with('info', 'La creacion de roles adicionales esta deshabilitada.');
    }

    public function edit(Role $role)
    {
        abort_unless(in_array($role->name, self::ALLOWED_ROLE_NAMES, true), 404);

        $role->load('permissions');

        return view('admin.roles.form', [
            'pageTitle' => 'Editar rol',
            'roleModel' => $role,
            'permissionGroups' => $this->permissionGroups(),
            'selectedPermissions' => old('permissions', $role->permissions->pluck('id')->all()),
            'formAction' => route('admin.roles.update', $role),
            'submitLabel' => 'Actualizar rol',
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_unless(in_array($role->name, self::ALLOWED_ROLE_NAMES, true), 404);

        $validated = $this->validateRoleData($request, $role);

        if ($role->name === 'Admin' && $validated['name'] !== 'Admin') {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'El rol Admin no puede renombrarse porque protege el acceso administrativo.');
        }

        $role->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        $role->permissions()->sync($validated['permissions'] ?? []);

        return redirect()
            ->route('admin.roles.index')
            ->with('success', 'Rol actualizado correctamente.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        return redirect()
            ->route('admin.roles.index')
            ->with('info', 'La eliminacion de roles base esta deshabilitada.');
    }

    private function validateRoleData(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::in(self::ALLOWED_ROLE_NAMES), Rule::unique('roles', 'name')->ignore($role?->id)],
            'description' => ['nullable', 'string'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);
    }

    private function permissionGroups(): Collection
    {
        return Permission::query()
            ->orderBy('name')
            ->get(['id', 'name', 'description'])
            ->groupBy(function (Permission $permission) {
                return Str::of($permission->name)->before('.')->headline()->toString();
            });
    }
}
