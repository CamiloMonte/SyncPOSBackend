<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $compra = (float) $this->precio_compra;
        $venta = (float) $this->precio_venta;
        $ganancia = $venta - $compra;

        return [
            'id' => $this->id,
            'codigo_barras' => $this->codigo_barras,
            'nombre' => $this->nombre,
            // whenLoaded: solo se incluye si la relación fue cargada (evita consultas extra).
            'categoria' => $this->whenLoaded('categoria', fn () => [
                'id' => $this->categoria->id,
                'nombre' => $this->categoria->nombre,
            ]),
            'precio_compra' => $this->precio_compra,
            'precio_venta' => $this->precio_venta,
            'ganancia_unitaria' => round($ganancia, 2),
            // Margen sobre el precio de venta, en porcentaje con 1 decimal.
            'margen' => $venta > 0 ? round($ganancia / $venta * 100, 1) : 0,
            'stock' => $this->stock,
            'stock_minimo' => $this->stock_minimo,
            'stock_bajo' => $this->stock_bajo,
            'activo' => $this->activo,
        ];
    }
}
