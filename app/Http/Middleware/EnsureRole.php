<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware: código que se ejecuta ANTES de llegar al controlador.
 * Este revisa que el usuario autenticado tenga uno de los roles permitidos.
 * Uso en rutas: ->middleware('rol:admin') o ->middleware('rol:admin,cajero')
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $usuario = $request->user();

        // Un usuario desactivado pierde el acceso aunque conserve su token.
        if (! $usuario || ! $usuario->activo || ! in_array($usuario->role?->nombre, $roles, true)) {
            return response()->json(['message' => 'No tienes permiso para esta acción.'], 403);
        }

        return $next($request);
    }
}
