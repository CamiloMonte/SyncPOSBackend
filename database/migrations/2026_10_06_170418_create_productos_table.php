<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_barras', 50)->unique();
            $table->string('nombre', 150);
            // restrictOnDelete: una categoría con productos no se puede borrar.
            $table->foreignId('categoria_id')->constrained('categorias')->restrictOnDelete();
            $table->decimal('precio_compra', 12, 2);
            $table->decimal('precio_venta', 12, 2);
            // Con signo a propósito: la sincronización offline puede dejar el stock en negativo.
            $table->integer('stock')->default(0);
            $table->unsignedInteger('stock_minimo')->default(5);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
