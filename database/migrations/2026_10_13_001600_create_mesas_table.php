<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mesas del salón.
 *
 * La mesa tiene un estado que es de operación, no de negocio: el mismo concepto
 * de "estado de mesa" vive en la orden abierta, no acá. Acá solo está la
 * disposición física: cuántas sillas tiene y si está habilitada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mesas', function (Blueprint $table) {
            $table->id();

            $table->string('nombre', 30);
            $table->string('codigo', 20)->unique();

            $table->foreignId('zona_id')->nullable();
            $table->unsignedSmallInteger('sillas')->default(4);

            /** Posición en el mapa del salón, para el plano del mesero. */
            $table->unsignedSmallInteger('pos_x')->default(0);
            $table->unsignedSmallInteger('pos_y')->default(0);

            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['zona_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mesas');
    }
};
