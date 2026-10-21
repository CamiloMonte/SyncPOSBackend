<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest: clase dedicada a validar los datos de una petición.
 * Si la validación falla, Laravel responde 422 automáticamente.
 */
class StoreCategoriaRequest extends FormRequest
{
    // La autorización por rol ya la hace el middleware "rol:admin".
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100', 'unique:categorias,nombre'],
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
