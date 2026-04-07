<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // Si el usuario no est� autenticado, redirigir al login
        if (!Auth::check()) {
            return redirect()->route('login')
                ->with('error', 'Debe iniciar sesi�n para acceder a este recurso');
        }

        // Si no se especificaron roles, permitir acceso
        if (empty($roles)) {
            return $next($request);
        }

        // Verificar si el usuario tiene al menos uno de los roles requeridos
        if (Auth::user()->hasRole($roles)) {
            return $next($request);
        }

        // Si no tiene permiso, retornar error 403
        return response()->view('errors.403', [], 403);
    }
}
