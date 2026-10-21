<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            // restrictOnDelete: nunca se borra un usuario que ya tiene ventas (historial financiero).
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->decimal('total', 12, 2);
            $table->enum('metodo_pago', ['efectivo', 'nequi']);
            $table->enum('origen', ['online', 'offline'])->default('online');
            // UUID generado por la app Android; evita duplicar una venta al sincronizar dos veces.
            $table->uuid('offline_referencia')->nullable()->unique();
            $table->timestamp('vendida_en')->index(); // momento real de la venta
            $table->timestamp('sincronizada_en')->nullable(); // momento en que llegó al servidor
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
