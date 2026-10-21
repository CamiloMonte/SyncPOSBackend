<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVentaRequest;
use App\Http\Resources\VentaResource;
use App\Models\Venta;
use App\Services\VentaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VentaController extends Controller
{
    // GET /api/ventas: el admin ve todas; el cajero solo las suyas.
    public function index(Request $request)
    {
        $usuario = $request->user();
        $consulta = Venta::with(['user', 'detalles.producto'])->orderByDesc('vendida_en');

        if (! $usuario->esAdmin()) {
            $consulta->where('user_id', $usuario->id);
        }

        if ($buscar = trim((string) $request->query('buscar'))) {
            $consulta->where(function ($q) use ($buscar) {
                // Acepta "12" o "#V-12": se dejan solo los dígitos para buscar por id.
                $numero = preg_replace('/\D/', '', $buscar);
                if ($numero !== '') {
                    $q->where('id', (int) $numero);
                }
                $q->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$buscar}%"));
            });
        }

        if ($userId = $request->query('user_id')) {
            $consulta->where('user_id', $userId);
        }

        if ($metodo = $request->query('metodo_pago')) {
            $consulta->where('metodo_pago', $metodo);
        }

        if ($origen = $request->query('origen')) {
            $consulta->where('origen', $origen);
        }

        if ($desde = $request->query('desde')) {
            $consulta->where('vendida_en', '>=', $desde.' 00:00:00');
        }

        if ($hasta = $request->query('hasta')) {
            $consulta->where('vendida_en', '<=', $hasta.' 23:59:59');
        }

        return VentaResource::collection($consulta->paginate(15));
    }

    // GET /api/ventas/{venta}
    public function show(Request $request, Venta $venta)
    {
        $usuario = $request->user();

        // Un cajero no puede ver ventas de otro cajero.
        if (! $usuario->esAdmin() && $venta->user_id !== $usuario->id) {
            return response()->json(['message' => 'No tienes permiso para esta acción.'], 403);
        }

        return new VentaResource($venta->load(['user', 'detalles.producto']));
    }

    // POST /api/ventas: registra una venta en línea.
    public function store(StoreVentaRequest $request, VentaService $servicio)
    {
        // Transacción: o se guarda todo (venta, líneas y stock) o no se guarda nada.
        $venta = DB::transaction(fn () => $servicio->crearVenta(
            $request->user(),
            $request->input('metodo_pago'),
            $request->input('items'),
        ));

        return (new VentaResource($venta->load(['user', 'detalles.producto'])))
            ->response()
            ->setStatusCode(201);
    }
}
