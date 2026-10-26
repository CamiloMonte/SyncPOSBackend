<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\EstadoController;
use App\Http\Controllers\MisVentasController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\SyncVentaController;
use App\Http\Controllers\VentaController;
use Illuminate\Support\Facades\Route;

// Todas las rutas de este archivo llevan el prefijo /api automáticamente.

// --- Autenticación (pública) ---
// throttle:10,1 = máximo 10 intentos por minuto (protege contra fuerza bruta).
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

// --- Estado del servidor y de la base de datos (publico, sin token) ---
Route::get('/estado', EstadoController::class)->middleware('throttle:60,1');

// --- Rutas protegidas: requieren token de Sanctum ---
Route::middleware('auth:sanctum')->group(function () {

    // Autenticación
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Módulos abiertos a administrador y cajero
    Route::middleware('rol:admin,cajero')->group(function () {
        // Catálogo (solo lectura)
        Route::get('/categorias', [CategoriaController::class, 'index']);
        Route::get('/productos', [ProductoController::class, 'index']);
        Route::get('/productos/{producto}', [ProductoController::class, 'show']);

        // Ventas e historial
        Route::post('/ventas', [VentaController::class, 'store']);
        Route::get('/ventas', [VentaController::class, 'index']);
        Route::get('/ventas/{venta}', [VentaController::class, 'show']);

        // Sincronización de ventas offline
        Route::post('/sync/ventas', [SyncVentaController::class, 'store']);

        // Resumen del cajero
        Route::get('/mis-ventas/resumen', [MisVentasController::class, 'resumen']);
    });

    // Módulos solo para administrador
    Route::middleware('rol:admin')->group(function () {
        // Gestión de catálogo
        Route::post('/categorias', [CategoriaController::class, 'store']);
        Route::put('/categorias/{categoria}', [CategoriaController::class, 'update']);
        Route::post('/productos', [ProductoController::class, 'store']);
        Route::put('/productos/{producto}', [ProductoController::class, 'update']);
        Route::patch('/productos/{producto}/estado', [ProductoController::class, 'cambiarEstado']);

        // Reportes
        Route::get('/reportes/rentabilidad', [ReporteController::class, 'rentabilidad']);
        Route::get('/reportes/resumen-dia', [ReporteController::class, 'resumenDia']);
    });
});
