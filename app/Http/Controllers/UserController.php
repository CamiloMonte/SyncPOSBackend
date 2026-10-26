<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUsuarioRequest;
use App\Http\Requests\UpdateUsuarioRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    // GET /api/usuarios?buscar=&rol=&activo= (solo admin). Sin paginar: la lista es pequena.
    public function index(Request $request)
    {
        // withCount('ventas') agrega la columna ventas_count con una sola consulta.
        $consulta = User::with('role')->withCount('ventas')->orderBy('name');

        if ($buscar = $request->query('buscar')) {
            $consulta->where(function ($q) use ($buscar) {
                $q->where('name', 'like', "%{$buscar}%")
                    ->orWhere('email', 'like', "%{$buscar}%");
            });
        }

        if ($rol = $request->query('rol')) {
            // whereHas filtra por una columna de la relacion (la tabla roles).
            $consulta->whereHas('role', fn ($q) => $q->where('nombre', $rol));
        }

        // Se compara con null/vacio para distinguir "no se envio" de "activo=false".
        if ($request->query('activo') !== null && $request->query('activo') !== '') {
            $consulta->where('activo', $request->boolean('activo'));
        }

        return UserResource::collection($consulta->get());
    }

    // GET /api/usuarios/{user}
    public function show(User $user)
    {
        return new UserResource($user->load('role')->loadCount('ventas'));
    }

    // POST /api/usuarios (solo admin)
    public function store(StoreUsuarioRequest $request)
    {
        $datos = $request->validated();

        $user = User::create([
            'name' => $datos['name'],
            'email' => $datos['email'],
            // El modelo tiene el cast 'hashed', asi que la contrasena se guarda cifrada.
            'password' => $datos['password'],
            'role_id' => $this->idDeRol($datos['rol']),
            'activo' => $datos['activo'] ?? true,
        ]);

        return (new UserResource($user->load('role')->loadCount('ventas')))->response()->setStatusCode(201);
    }

    // PUT /api/usuarios/{user} (solo admin)
    public function update(UpdateUsuarioRequest $request, User $user)
    {
        $datos = $request->validated();

        // Un admin no puede quitarse el rol a si mismo: se podria quedar sin administradores.
        if ($request->user()->is($user) && $datos['rol'] !== 'admin') {
            throw ValidationException::withMessages(['rol' => 'No puedes quitarte el rol de administrador.']);
        }

        $cambios = [
            'name' => $datos['name'],
            'email' => $datos['email'],
            'role_id' => $this->idDeRol($datos['rol']),
        ];

        if (isset($datos['activo'])) {
            $cambios['activo'] = $datos['activo'];
        }

        // La contrasena solo cambia si viene con valor.
        $cambioPassword = ! empty($datos['password']);
        if ($cambioPassword) {
            $cambios['password'] = $datos['password'];
        }

        $user->update($cambios);

        // Con contrasena nueva, las sesiones abiertas dejan de servir.
        if ($cambioPassword) {
            $user->tokens()->delete();
        }

        return new UserResource($user->load('role')->loadCount('ventas'));
    }

    // PATCH /api/usuarios/{user}/estado (solo admin): activa o desactiva la cuenta.
    // No existe endpoint DELETE: las ventas referencian al usuario (historial financiero),
    // por eso una cuenta se desactiva, no se elimina.
    public function cambiarEstado(Request $request, User $user)
    {
        if ($request->user()->is($user)) {
            return response()->json(['message' => 'No puedes desactivar tu propia cuenta.'], 422);
        }

        $user->update(['activo' => ! $user->activo]);

        // Al desactivar se borran sus tokens: sus sesiones abiertas dejan de funcionar
        // de inmediato (el login ya devuelve 403 para usuarios inactivos).
        if (! $user->activo) {
            $user->tokens()->delete();
        }

        return new UserResource($user->load('role')->loadCount('ventas'));
    }

    // Convierte el nombre del rol ("admin" o "cajero") en el id de la tabla roles.
    private function idDeRol(string $nombre): int
    {
        return Role::firstOrCreate(['nombre' => $nombre])->id;
    }
}
