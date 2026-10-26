<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class EstadoController extends Controller
{
    // GET /api/estado (publico): sirve para saber si la API y la base de datos responden.
    public function __invoke(): JsonResponse
    {
        try {
            // Una consulta real: si la base de datos esta caida, lanza una excepcion.
            DB::select('select 1');
        } catch (Throwable) {
            // No se devuelve el detalle del error para no filtrar informacion interna.
            return response()->json([
                'estado' => 'error',
                'base_de_datos' => 'sin conexión',
                'message' => 'No se pudo conectar con la base de datos.',
            ], 503);
        }

        return response()->json([
            'estado' => 'ok',
            'base_de_datos' => 'conectada',
            'servidor' => 'SyncPOS API',
            'hora' => now()->toIso8601String(),
        ]);
    }
}
