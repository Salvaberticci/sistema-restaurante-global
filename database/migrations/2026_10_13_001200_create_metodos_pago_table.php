<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Métodos de pago y su perfil de IGTF.
 *
 * `es_divisa` es lo que separa "efectivo en bolívares" de "efectivo en
 * dólares". Solo los métodos con `es_divisa = true` pueden generar IGTF, y cada
 * uno tiene su propio perfil, porque la alícuota depende de la modalidad y no
 * solo de que sea en divisa.
 *
 * `requiere_referencia` está activo para pago móvil, transferencia y Zelle: sin
 * el número de operación la conciliación con el banco es imposible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metodos_pago', function (Blueprint $table) {
            $table->id();

            $table->string('codigo', 30)->unique();
            $table->string('nombre', 80);
            $table->string('icono', 60)->nullable();

            $table->boolean('es_divisa')->default(false);
            $table->boolean('es_efectivo')->default(false);
            $table->boolean('requiere_referencia')->default(false);
            $table->boolean('genera_igtf')->default(false);
            $table->string('igtf_perfil_codigo', 40)->nullable();

            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);

            $table->timestamps();

            $table->index(['activo', 'orden']);
            $table->index('es_divisa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metodos_pago');
    }
};
