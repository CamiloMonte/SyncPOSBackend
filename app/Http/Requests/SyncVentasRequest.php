<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncVentasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // Solo se valida la forma del lote. Si un producto no existe o está inactivo,
    // esa venta se marca como "rechazada" en la respuesta y las demás se procesan.
    public function rules(): array
    {
        return [
            'ventas' => ['required', 'array', 'min:1', 'max:100'],
            'ventas.*.offline_referencia' => ['required', 'uuid'],
            'ventas.*.metodo_pago' => ['required', 'in:efectivo,nequi'],
            'ventas.*.vendida_en' => ['required', 'date'],
            // Opcional: "online" si el celular la envio apenas se hizo; por defecto "offline".
            'ventas.*.origen' => ['sometimes', 'in:online,offline'],
            'ventas.*.items' => ['required', 'array', 'min:1'],
            'ventas.*.items.*.producto_id' => ['required', 'integer'],
            'ventas.*.items.*.cantidad' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'ventas.max' => 'Se pueden sincronizar máximo 100 ventas por petición.',
            'ventas.*.offline_referencia.uuid' => 'La referencia offline debe ser un UUID válido.',
        ];
    }
}
