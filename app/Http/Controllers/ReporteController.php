<?php

namespace App\Http\Controllers;

use App\Http\Resources\VentaResource;
use App\Models\Producto;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReporteController extends Controller
{
    // GET /api/reportes/rentabilidad?desde=&hasta= (por defecto, el mes actual)
    public function rentabilidad(Request $request): JsonResponse
    {
        $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $desde = $request->filled('desde') ? Carbon::parse($request->query('desde'))->startOfDay() : now()->startOfMonth();
        $hasta = $request->filled('hasta') ? Carbon::parse($request->query('hasta'))->endOfDay() : now()->endOfMonth();

        // Paso 1: se une detalle_ventas con ventas (para filtrar por fecha) y con productos (para el nombre).
        // Paso 2: se agrupa por producto.
        // Paso 3: ingresos = suma de subtotales; costo = suma de cantidad * costo_unitario.
        // Se usan los valores guardados en la venta (instantánea), no los precios actuales del producto.
        $filas = DB::table('detalle_ventas as d')
            ->join('ventas as v', 'v.id', '=', 'd.venta_id')
            ->join('productos as p', 'p.id', '=', 'd.producto_id')
            ->whereBetween('v.vendida_en', [$desde, $hasta])
            ->groupBy('d.producto_id', 'p.nombre')
            ->selectRaw('d.producto_id, p.nombre, SUM(d.cantidad) as unidades, SUM(d.subtotal) as ingresos, SUM(d.cantidad * d.costo_unitario) as costo')
            ->get();

        // Paso 4: totales generales del periodo.
        $ingresosTotal = (float) $filas->sum('ingresos');
        $costoTotal = (float) $filas->sum('costo');
        $gananciaTotal = $ingresosTotal - $costoTotal;

        // Paso 5: cálculo por producto, ordenado por ganancia de mayor a menor.
        $productos = $filas->map(function ($fila) use ($gananciaTotal) {
            $ingresos = (float) $fila->ingresos;
            $costo = (float) $fila->costo;
            $ganancia = $ingresos - $costo;

            return [
                'producto_id' => $fila->producto_id,
                'nombre' => $fila->nombre,
                'unidades' => (int) $fila->unidades,
                'ingresos' => round($ingresos, 2),
                'costo' => round($costo, 2),
                'ganancia' => round($ganancia, 2),
                'margen' => $ingresos > 0 ? round($ganancia / $ingresos * 100, 1) : 0,
                // Qué porcentaje de la ganancia total aporta este producto.
                'contribucion' => $gananciaTotal > 0 ? round($ganancia / $gananciaTotal * 100, 1) : 0,
            ];
        })->sortByDesc('ganancia')->values();

        return response()->json([
            'periodo' => ['desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString()],
            'totales' => [
                'ingresos' => round($ingresosTotal, 2),
                'costo' => round($costoTotal, 2),
                'ganancia' => round($gananciaTotal, 2),
                'margen' => $ingresosTotal > 0 ? round($gananciaTotal / $ingresosTotal * 100, 1) : 0,
            ],
            'productos' => $productos,
        ]);
    }

    // GET /api/reportes/resumen-dia?fecha= (por defecto, hoy): datos del dashboard.
    public function resumenDia(Request $request): JsonResponse
    {
        $request->validate(['fecha' => ['nullable', 'date']]);

        $fecha = $request->filled('fecha') ? Carbon::parse($request->query('fecha')) : now();
        $dia = $this->totalesDelDia($fecha);

        // Últimos 7 días, terminando en la fecha consultada.
        $ultimos7 = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = $fecha->copy()->subDays($i);
            $totales = $this->totalesDelDia($d);
            $ultimos7[] = [
                'fecha' => $d->toDateString(),
                'ventas' => $totales['ventas'],
                'ganancia' => $totales['ganancia'],
            ];
        }

        $activos = Producto::where('activo', true);

        return response()->json([
            'ventas_total' => $dia['ventas'],
            'pedidos' => $dia['pedidos'],
            'ganancia_neta' => $dia['ganancia'],
            // Dinero invertido en inventario: stock * precio de compra (solo stock positivo).
            'inversion_inventario' => round((float) (clone $activos)->where('stock', '>', 0)->sum(DB::raw('stock * precio_compra')), 2),
            'alertas_stock' => (clone $activos)->whereColumn('stock', '<=', 'stock_minimo')->count(),
            'ultimas_ventas' => VentaResource::collection(
                Venta::with(['user', 'detalles.producto'])->orderByDesc('vendida_en')->limit(5)->get()
            ),
            'ultimos_7_dias' => $ultimos7,
        ]);
    }

    // Calcula ventas, número de pedidos y ganancia de un día.
    private function totalesDelDia(Carbon $dia): array
    {
        $rango = [$dia->copy()->startOfDay(), $dia->copy()->endOfDay()];

        $ventas = Venta::whereBetween('vendida_en', $rango);

        $ganancia = DB::table('detalle_ventas as d')
            ->join('ventas as v', 'v.id', '=', 'd.venta_id')
            ->whereBetween('v.vendida_en', $rango)
            ->sum(DB::raw('d.subtotal - d.cantidad * d.costo_unitario'));

        return [
            'ventas' => round((float) (clone $ventas)->sum('total'), 2),
            'pedidos' => (clone $ventas)->count(),
            'ganancia' => round((float) $ganancia, 2),
        ];
    }
}
