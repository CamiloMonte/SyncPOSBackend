<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            // confirmed: exige un campo password_confirmation con el mismo valor.
            'password' => ['required', 'string', 'min:8', 'confirmed'],
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
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
            'rol.required' => 'El rol es obligatorio.',
            'rol.in' => 'El rol debe ser administrador o cajero.',
            'activo.boolean' => 'El estado no es válido.',
        ];
    }
}
