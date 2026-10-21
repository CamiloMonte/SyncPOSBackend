<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'metodo_pago' => ['required', 'in:efectivo,nequi'],
            'items' => ['required', 'array', 'min:1'],
            // "items.*" aplica la regla a cada elemento del arreglo.
            // Nota: no se pide precio; el servidor lo toma de la base de datos.
            'items.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'items.*.cantidad' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'metodo_pago.in' => 'El método de pago debe ser efectivo o nequi.',
            'items.required' => 'La venta debe tener al menos un producto.',
            'items.min' => 'La venta debe tener al menos un producto.',
            'items.*.producto_id.exists' => 'Uno de los productos no existe.',
            'items.*.cantidad.min' => 'La cantidad debe ser al menos 1.',
        ];
    }
}
