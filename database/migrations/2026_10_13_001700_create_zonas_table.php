<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zonas y salones: el comedor, la barra, la terraza y el salón de eventos.
 *
 * Sirven para lo mismo en dos escalas distintas, y por eso comparten tabla: un
 * comedor tiene mesas, un salón de eventos tiene un número de personas y un
 * tiempo de rotación entre una reserva y la siguiente.
 *
 * `turnover_minutos` es lo que permite decir "con este salón y esta rotación,
 * el sábado hay espacio para cuatro eventos", que es la pregunta que hace un
 * dueño cuando quiere reservar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zonas', function (Blueprint $table) {
            $table->id();

            $table->string('nombre', 80);
            $table->string('tipo', 30)->default('salon');
            // salon | barra | terraza | eventos

            $table->unsignedSmallInteger('capacidad')->default(0);
            $table->unsignedSmallInteger('mesas')->default(0);
            $table->unsignedSmallInteger('turnover_minutos')->default(75);

            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['activo', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zonas');
    }
};
