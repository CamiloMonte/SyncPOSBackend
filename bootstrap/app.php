<?php

use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Alias corto para usar "rol:admin" en las rutas.
        $middleware->alias([
            'rol' => EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Todas las respuestas de error bajo /api se devuelven como JSON (nunca como HTML).
        $exceptions->shouldRenderJsonWhen(fn ($request) => $request->is('api/*'));
    })->create();
