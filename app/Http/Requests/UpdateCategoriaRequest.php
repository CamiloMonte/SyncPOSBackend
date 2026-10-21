<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ignore(): al editar, el nombre actual de la misma categoría no cuenta como repetido.
            'nombre' => ['required', 'string', 'max:100', Rule::unique('categorias', 'nombre')->ignore($this->route('categoria'))],
            'descripcion' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la categoría es obligatorio.',
            'nombre.unique' => 'Ya existe una categoría con ese nombre.',
        ];
    }
}
