<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Show the login form
     */
    public function showLogin()
    {
        // Si el usuario ya está autenticado, redirigir al dashboard
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    /**
     * Handle login request
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|min:6',
        ], [
            'email.required' => 'El correo es requerido',
            'email.email' => 'El correo debe ser válido',
            'password.required' => 'La contraseña es requerida',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput($request->only('email'));
        }

        // Intentar autenticar
        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()
                ->intended(route('dashboard'))
                ->with('success', 'Bienvenido al sistema de gestión de restaurante');
        }

        return back()
            ->withErrors(['email' => 'Las credenciales proporcionadas no coinciden con nuestros registros.'])
            ->withInput($request->only('email'));
    }

    /**
     * Handle logout request
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', 'Sesión cerrada correctamente');
    }

    /**
     * Show the user's role information
     */
    public function getCurrentUser()
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'No authenticado'], 401);
        }

        $user = Auth::user();
        $user->load('roles.permissions');

        return response()->json([
            'user' => $user,
            'roles' => $user->roles->pluck('name'),
            'permissions' => $user->roles->flatMap(function ($role) {
                return $role->permissions->pluck('name');
            })->unique()->values(),
        ]);
    }

    /**
     * Check if user has specific role
     */
    public function hasRole(Request $request, string $role)
    {
        if (!Auth::check()) {
            return response()->json(['has_role' => false]);
        }

        return response()->json([
            'has_role' => Auth::user()->hasRole($role),
        ]);
    }

    /**
     * Check if user has specific permission
     */
    public function hasPermission(Request $request, string $permission)
    {
        if (!Auth::check()) {
            return response()->json(['has_permission' => false]);
        }

        return response()->json([
            'has_permission' => Auth::user()->hasPermission($permission),
        ]);
    }
}
