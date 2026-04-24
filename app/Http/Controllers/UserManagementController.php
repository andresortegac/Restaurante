<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
        ]);

        $users = User::query()
            ->with('roles')
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($nestedQuery) use ($search) {
                    $nestedQuery
                        ->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                });
            })
            ->when($filters['role_id'] ?? null, function ($query, int $roleId) {
                $query->whereHas('roles', fn ($roleQuery) => $roleQuery->where('roles.id', $roleId));
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'filters' => $filters,
            'roles' => $this->availableRoles(),
            'summary' => [
                'total' => User::count(),
                'admins' => User::whereHas('roles', fn ($query) => $query->where('name', 'Admin'))->count(),
                'nonAdmins' => User::whereDoesntHave('roles', fn ($query) => $query->where('name', 'Admin'))->count(),
            ],
        ]);
    }

    public function create()
    {
        return view('admin.users.form', [
            'pageTitle' => 'Nuevo usuario',
            'userModel' => new User(),
            'roles' => $this->availableRoles(),
            'selectedRoles' => old('roles', []),
            'formAction' => route('admin.users.store'),
            'submitLabel' => 'Guardar usuario',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateUserData($request);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->roles()->sync($validated['roles']);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Usuario creado correctamente.');
    }

    public function edit(User $user)
    {
        $user->load('roles');

        return view('admin.users.form', [
            'pageTitle' => 'Editar usuario',
            'userModel' => $user,
            'roles' => $this->availableRoles(),
            'selectedRoles' => old('roles', $user->roles->pluck('id')->all()),
            'formAction' => route('admin.users.update', $user),
            'submitLabel' => 'Actualizar usuario',
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $this->validateUserData($request, $user);

        if ($user->is(auth()->user()) && !Role::whereIn('id', $validated['roles'])->where('name', 'Admin')->exists()) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Tu usuario debe conservar el rol Admin para mantener acceso a este modulo.');
        }

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if (!empty($validated['password'])) {
            $user->update([
                'password' => Hash::make($validated['password']),
            ]);
        }

        $user->roles()->sync($validated['roles']);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->is(auth()->user())) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'No puedes eliminar tu propio usuario mientras tienes la sesion abierta.');
        }

        if ($user->hasRole('Admin') && User::whereHas('roles', fn ($query) => $query->where('name', 'Admin'))->count() <= 1) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'No puedes eliminar el ultimo usuario con rol Admin.');
        }

        if ($user->sales()->exists() || $user->boxMovements()->exists() || $user->reservations()->exists()) {
            return redirect()
                ->route('admin.users.index')
                ->with('warning', 'El usuario ya tiene movimientos registrados y no puede eliminarse.');
        }

        $user->roles()->detach();
        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Usuario eliminado correctamente.');
    }

    private function validateUserData(Request $request, ?User $user = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ]);

        $validated['roles'] = array_values(array_unique(array_map('intval', $validated['roles'])));

        return $validated;
    }

    private function availableRoles()
    {
        return Role::orderBy('name')->get(['id', 'name', 'description']);
    }
}
