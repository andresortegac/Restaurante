<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        // Si el usuario no estï¿½ autenticado, redirigir al login
        if (!Auth::check()) {
            return redirect()->route('login')
                ->with('error', 'Debe iniciar sesiï¿½n para acceder a este recurso');
        }

        // Si no se especificaron permisos, permitir acceso
        if (empty($permissions)) {
            return $next($request);
        }

        // Verificar si el usuario tiene al menos uno de los permisos requeridos
        if (Auth::user()->hasAnyPermission($permissions)) {
            return $next($request);
        }

        // Si no tiene permiso, retornar error 403
        return response()->view('errors.403', [], 403);
    }
}
