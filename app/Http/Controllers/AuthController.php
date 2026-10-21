<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // POST /api/login: valida credenciales y entrega un token de Sanctum.
    public function login(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $usuario = User::with('role')->where('email', $datos['email'])->first();

        if (! $usuario || ! Hash::check($datos['password'], $usuario->password)) {
            // ValidationException responde 422 con el mensaje indicado.
            throw ValidationException::withMessages(['email' => 'Credenciales incorrectas.']);
        }

        if (! $usuario->activo) {
            return response()->json(['message' => 'Usuario inactivo.'], 403);
        }

        return response()->json([
            'token' => $usuario->createToken('syncpos')->plainTextToken,
            'user' => $this->datosUsuario($usuario),
        ]);
    }

    // POST /api/logout: elimina solo el token con el que se hizo la petición.
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    // GET /api/me: datos del usuario autenticado.
    public function me(Request $request): JsonResponse
    {
        return response()->json($this->datosUsuario($request->user()->load('role')));
    }

    private function datosUsuario(User $usuario): array
    {
        return [
            'id' => $usuario->id,
            'name' => $usuario->name,
            'email' => $usuario->email,
            'rol' => $usuario->role?->nombre,
        ];
    }
}
