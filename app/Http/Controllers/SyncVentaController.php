<?php

namespace App\Http\Controllers;

use App\Http\Requests\SyncVentasRequest;
use App\Models\Producto;
use App\Models\Venta;
use App\Services\VentaService;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SyncVentaController extends Controller
{
    // POST /api/sync/ventas: recibe las ventas hechas sin internet en la app Android.
    public function store(SyncVentasRequest $request, VentaService $servicio): JsonResponse
    {
        $resultados = [];

        foreach ($request->input('ventas') as $datos) {
            $referencia = $datos['offline_referencia'];

            // Idempotencia: si esta referencia ya se guardó, no se hace nada y se devuelve la venta existente.
            $existente = Venta::where('offline_referencia', $referencia)->first();
            if ($existente) {
                $resultados[] = [
                    'offline_referencia' => $referencia,
                    'estado' => 'duplicada',
                    'venta_id' => $existente->id,
                ];

                continue;
            }

            try {
                // Cada venta tiene su propia transacción: si una falla, las demás siguen.
                $venta = DB::transaction(fn () => $servicio->crearVenta(
                    $request->user(),
                    $datos['metodo_pago'],
                    $datos['items'],
                    // La app avisa si la venta se envio al instante ("online") o si espero
                    // sin conexion ("offline"). Si no lo dice, se asume "offline".
                    $datos['origen'] ?? 'offline',
                    Carbon::parse($datos['vendida_en']), // se conserva la hora real de la venta
                    $referencia,
                    // La venta ya ocurrió físicamente en la tienda, por eso se acepta aunque el
                    // stock quede en negativo; el administrador revisa el inventario después.
                    permitirStockNegativo: true,
                ));
            } catch (ValidationException $e) {
                $resultados[] = [
                    'offline_referencia' => $referencia,
                    'estado' => 'rechazada',
                    'mensaje' => collect($e->errors())->flatten()->first(),
                ];

                continue;
            } catch (UniqueConstraintViolationException) {
                // Otra petición guardó esta misma venta al mismo tiempo (por ejemplo, un reintento
                // del celular por timeout). El índice único de offline_referencia la frenó: es duplicada.
                $resultados[] = [
                    'offline_referencia' => $referencia,
                    'estado' => 'duplicada',
                    'venta_id' => Venta::where('offline_referencia', $referencia)->value('id'),
                ];

                continue;
            }

            // Productos de esta venta que quedaron con stock negativo.
            $negativos = Producto::whereIn('id', collect($datos['items'])->pluck('producto_id'))
                ->where('stock', '<', 0)
                ->pluck('nombre')
                ->all();

            $resultado = [
                'offline_referencia' => $referencia,
                'estado' => 'sincronizada',
                'venta_id' => $venta->id,
            ];

            if ($negativos) {
                $resultado['stock_negativo'] = true;
                $resultado['mensaje'] = 'Stock negativo en: '.implode(', ', $negativos).'.';
                $resultado['productos_stock_negativo'] = $negativos;
            }

            $resultados[] = $resultado;
        }

        return response()->json(['resultados' => $resultados]);
    }
}
