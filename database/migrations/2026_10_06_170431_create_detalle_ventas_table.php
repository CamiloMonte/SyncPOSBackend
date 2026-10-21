<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_ventas', function (Blueprint $table) {
            $table->id();
            // Las líneas pertenecen a su venta: si se borra la venta, se borran sus líneas.
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->unsignedInteger('cantidad');
            // Instantánea (snapshot) de precios al momento de la venta. Es importante porque
            // los reportes de ganancia no deben cambiar cuando después cambie el precio de compra.
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('costo_unitario', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_ventas');
    }
};
