<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ítems de la orden.
 *
 * `precio_unitario` es una COPIA del precio del catálogo en el momento de pedir,
 * no una referencia. Si mañana suben el pabellón de 7,50 a 8,00, las órdenes de
 * ayer tienen que seguir mostrando 7,50: ese número ya se le cobró a alguien y
 * es la evidencia de lo que se facturó.
 *
 * `notas` es lo que la cocina necesita literal: "sin cebolla", "término medio",
 * "para compartir". Es la mitad del valor de una tablet para el mesero.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orden_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('orden_id')->constrained('ordenes')->cascadeOnDelete();
            $table->foreignId('producto_id');

            // Snapshot del producto y del precio al momento del pedido.
            $table->string('producto_nombre', 120);
            $table->string('producto_codigo', 30)->nullable();

            $table->decimal('cantidad', 18, 4);
            $table->decimal('precio_unitario', 18, 4);
            $table->decimal('importe', 18, 4);

            $table->string('alicuota_codigo', 20)->default('IVA_G');

            $table->text('notas')->nullable();
            $table->string('estado', 30)->default('pendiente');
            // pendiente → preparando → listo → servido
            // exceptional: cortado

            $table->string('cortado_motivo', 120)->nullable();
            $table->foreignId('cortado_por')->nullable();

            $table->timestamps();

            $table->index(['orden_id', 'estado']);
            $table->index('producto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orden_items');
    }
};
