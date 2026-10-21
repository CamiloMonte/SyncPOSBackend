<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductoRequest;
use App\Http\Requests\UpdateProductoRequest;
use App\Http\Resources\ProductoResource;
use App\Models\Producto;
use Illuminate\Http\Request;

class ProductoController extends Controller
{
    // GET /api/productos con filtros opcionales.
    public function index(Request $request)
    {
        // with('categoria'): carga la categoría de una vez y evita una consulta por producto.
        $consulta = Producto::with('categoria')->orderBy('nombre');

        if ($buscar = $request->query('buscar')) {
            $consulta->where(function ($q) use ($buscar) {
                $q->where('nombre', 'like', "%{$buscar}%")
                    ->orWhere('codigo_barras', 'like', "%{$buscar}%");
            });
        }

        if ($categoriaId = $request->query('categoria_id')) {
            $consulta->where('categoria_id', $categoriaId);
        }

        if ($request->boolean('solo_activos')) {
            $consulta->where('activo', true);
        }

        if ($request->boolean('stock_bajo')) {
            // Compara dos columnas entre sí: stock <= stock_minimo.
            $consulta->whereColumn('stock', '<=', 'stock_minimo');
        }

        return ProductoResource::collection($consulta->paginate(20));
    }

    // GET /api/productos/{producto}
    public function show(Producto $producto)
    {
        return new ProductoResource($producto->load('categoria'));
    }

    // POST /api/productos (solo admin)
    public function store(StoreProductoRequest $request)
    {
        $producto = Producto::create($request->validated());

        return (new ProductoResource($producto->load('categoria')))->response()->setStatusCode(201);
    }

    // PUT /api/productos/{producto} (solo admin)
    public function update(UpdateProductoRequest $request, Producto $producto)
    {
        $producto->update($request->validated());

        return new ProductoResource($producto->load('categoria'));
    }

    // PATCH /api/productos/{producto}/estado (solo admin): activa o desactiva el producto.
    // No existe endpoint para borrar: un producto con ventas se desactiva, no se elimina,
    // para no perder el historial de ventas.
    public function cambiarEstado(Producto $producto)
    {
        $producto->update(['activo' => ! $producto->activo]);

        return new ProductoResource($producto->load('categoria'));
    }
}
