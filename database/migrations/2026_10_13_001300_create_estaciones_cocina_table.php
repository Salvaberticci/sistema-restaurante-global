<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estaciones de cocina.
 *
 * Una orden se parte en N comandas, una por estación. Un plato de cocina caliente
 * no sale en la barra, y un café no espera en la cocina: si no se separan, la
 * pantalla se llena de tickets que el personal tiene que filtrar a ojo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estaciones_cocina', function (Blueprint $table) {
            $table->id();

            $table->string('codigo', 30)->unique();
            $table->string('nombre', 80);
            $table->string('descripcion', 255)->nullable();
            $table->string('icono', 60)->nullable();

            /** Minutos hasta que el ticket pasa de verde a ámbar. */
            $table->unsignedSmallInteger('tiempo_verde_min')->default(15);
            /** Minutos hasta que pasa de ámbar a rojo. */
            $table->unsignedSmallInteger('tiempo_ambar_min')->default(20);

            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['activo', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estaciones_cocina');
    }
};
