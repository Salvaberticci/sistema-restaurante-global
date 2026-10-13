<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Insumos: lo que se usa para producir, no lo que se vende.
 *
 * Un insumo no tiene precio de venta, pero sí precio de compra y un stock
 * mínimo. El stock se recalcula desde el kardex (spec 090); estas columnas son
 * el dato inicial y el umbral de alerta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insumos', function (Blueprint $table) {
            $table->id();

            $table->string('codigo', 30)->unique();
            $table->string('nombre', 120);
            $table->string('descripcion', 500)->nullable();

            $table->foreignId('unidad_medida_id')->nullable();

            $table->decimal('precio_compra', 18, 4)->nullable();
            $table->decimal('stock_inicial', 18, 4)->default(0);
            $table->decimal('stock_minimo', 18, 4)->default(0);

            $table->decimal('merma_porcentaje', 5, 2)->default(0);
            $table->string('alicuota_codigo', 20)->default('IVA_G');

            $table->string('proveedor', 120)->nullable();
            $table->boolean('activo')->default(true);

            $table->timestamps();

            $table->index(['activo', 'stock_minimo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insumos');
    }
};
