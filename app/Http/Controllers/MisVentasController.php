<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MisVentasController extends Controller
{
    // GET /api/mis-ventas/resumen?fecha= (por defecto, hoy): resumen de las ventas del propio usuario.
    public function resumen(Request $request): JsonResponse
    {
        $request->validate(['fecha' => ['nullable', 'date']]);

        $fecha = $request->filled('fecha') ? Carbon::parse($request->query('fecha')) : now();

        $ventas = Venta::where('user_id', $request->user()->id)
            ->whereBetween('vendida_en', [$fecha->copy()->startOfDay(), $fecha->copy()->endOfDay()])
            ->get();

        $porMetodo = fn (string $metodo) => [
            'total' => round((float) $ventas->where('metodo_pago', $metodo)->sum('total'), 2),
            'cantidad' => $ventas->where('metodo_pago', $metodo)->count(),
        ];

        return response()->json([
            'total' => round((float) $ventas->sum('total'), 2),
            'cantidad' => $ventas->count(),
            'efectivo' => $porMetodo('efectivo'),
            'nequi' => $porMetodo('nequi'),
            'offline' => ['cantidad' => $ventas->where('origen', 'offline')->count()],
        ]);
    }
}
