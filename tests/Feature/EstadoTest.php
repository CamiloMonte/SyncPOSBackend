<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EstadoTest extends TestCase
{
    public function test_estado_responde_ok_sin_autenticacion(): void
    {
        $this->getJson('/api/estado')
            ->assertOk()
            ->assertJsonPath('estado', 'ok')
            ->assertJsonPath('base_de_datos', 'conectada')
            ->assertJsonPath('servidor', 'SyncPOS API')
            ->assertJsonStructure(['hora']);
    }

    public function test_estado_responde_503_si_la_base_de_datos_falla(): void
    {
        // No se usa RefreshDatabase: este test no necesita tablas. Se apunta la conexion a un archivo sqlite que no existe para forzar el error.
        Config::set('database.connections.sqlite.database', '/ruta/que/no/existe.sqlite');
        DB::purge('sqlite');

        $this->getJson('/api/estado')
            ->assertStatus(503)
            ->assertJsonPath('estado', 'error')
            ->assertJsonPath('base_de_datos', 'sin conexión');
    }
}
