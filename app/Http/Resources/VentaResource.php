<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VentaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // El costo es información sensible: solo el administrador lo ve.
        $esAdmin = (bool) $request->user()?->esAdmin();

        return [
            'id' => $this->id,
            'numero' => '#V-'.$this->id,
            'cajero' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'metodo_pago' => $this->metodo_pago,
            'origen' => $this->origen,
            'total' => $this->total,
            'vendida_en' => $this->vendida_en?->toIso8601String(),
            'sincronizada_en' => $this->sincronizada_en?->toIso8601String(),
            'items' => $this->whenLoaded('detalles', fn () => $this->detalles->map(function ($d) use ($esAdmin) {
                $linea = [
                    'producto_id' => $d->producto_id,
                    'nombre' => $d->producto?->nombre,
                    'cantidad' => $d->cantidad,
                    'precio_unitario' => $d->precio_unitario,
                    'subtotal' => $d->subtotal,
                ];

                // El costo solo se agrega para el administrador.
                if ($esAdmin) {
                    $linea['costo_unitario'] = $d->costo_unitario;
                }

                return $linea;
            })->values()),
        ];
    }
}
