<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DEF-04 (informe de pruebas de humo): en MySQL/MariaDB la columna vendida_en era TIMESTAMP NOT NULL.
// Con explicit_defaults_for_timestamp = 0 (valor por defecto de XAMPP), MariaDB le agrega
// "ON UPDATE CURRENT_TIMESTAMP": cada vez que se actualizaba la venta (por ejemplo el total),
// la hora real se reemplazaba por la hora del servidor. Las ventas offline perdian su hora.
// DATETIME guarda exactamente el valor que se le envia y nunca se cambia solo.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            // change() redefine la columna completa; el indice de vendida_en se conserva
            $table->dateTime('vendida_en')->change();
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->timestamp('vendida_en')->change();
        });
    }
};
