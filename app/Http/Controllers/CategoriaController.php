<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoriaRequest;
use App\Http\Requests\UpdateCategoriaRequest;
use App\Http\Resources\CategoriaResource;
use App\Models\Categoria;

class CategoriaController extends Controller
{
    // GET /api/categorias: lista para cualquier usuario autenticado.
    public function index()
    {
        return CategoriaResource::collection(Categoria::orderBy('nombre')->get());
    }

    // POST /api/categorias (solo admin).
    public function store(StoreCategoriaRequest $request)
    {
        // validated() devuelve solo los campos que pasaron las reglas.
        $categoria = Categoria::create($request->validated());

        return (new CategoriaResource($categoria))->response()->setStatusCode(201);
    }

    // PUT /api/categorias/{categoria} (solo admin).
    public function update(UpdateCategoriaRequest $request, Categoria $categoria)
    {
        $categoria->update($request->validated());

        return new CategoriaResource($categoria);
    }
}
