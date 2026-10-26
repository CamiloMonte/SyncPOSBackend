<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource: define que campos del usuario se envian en el JSON.
 * Nunca se incluye la contrasena ni el token de recordar sesion.
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'rol' => $this->role?->nombre,
            'activo' => $this->activo,
            // ventas_count existe solo si la consulta uso withCount('ventas').
            'ventas_count' => (int) ($this->ventas_count ?? $this->ventas()->count()),
            'created_at' => $this->created_at,
        ];
    }
}
