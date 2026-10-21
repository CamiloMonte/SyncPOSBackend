<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo_barras' => ['required', 'string', 'max:50', 'unique:productos,codigo_barras'],
            'nombre' => ['required', 'string', 'max:150'],
            'categoria_id' => ['required', 'exists:categorias,id'],
            'precio_compra' => ['required', 'numeric', 'min:0'],
            // gte: el precio de venta debe ser mayor o igual al precio de compra.
            'precio_venta' => ['required', 'numeric', 'min:0', 'gte:precio_compra'],
            'stock' => ['required', 'integer', 'min:0'],
            'stock_minimo' => ['required', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo_barras.required' => 'El código de barras es obligatorio.',
            'codigo_barras.unique' => 'Ya existe un producto con ese código de barras.',
            'nombre.required' => 'El nombre es obligatorio.',
            'categoria_id.exists' => 'La categoría seleccionada no existe.',
            'precio_venta.gte' => 'El precio de venta no puede ser menor al precio de compra.',
            'stock.min' => 'El stock no puede ser negativo.',
            'stock_minimo.min' => 'El stock mínimo no puede ser negativo.',
        ];
    }
}
