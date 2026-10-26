<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            // ignore(): el usuario que se edita puede conservar su propio correo.
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            // nullable: si no se envia contrasena, se conserva la actual.
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'rol' => ['required', 'in:admin,cajero'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.max' => 'El nombre no puede superar los 150 caracteres.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'Ingresa un correo válido.',
            'email.unique' => 'Ya existe un usuario con ese correo.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
            'rol.required' => 'El rol es obligatorio.',
            'rol.in' => 'El rol debe ser administrador o cajero.',
            'activo.boolean' => 'El estado no es válido.',
        ];
    }
}
