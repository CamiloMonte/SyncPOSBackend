<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Se usa una sola tabla de usuarios (users) y se le agrega el rol.
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // restrictOnDelete: no se puede borrar un rol que tenga usuarios.
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            // En vez de borrar usuarios se desactivan, para conservar su historial de ventas.
            $table->boolean('activo')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn(['role_id', 'activo']);
        });
    }
};
