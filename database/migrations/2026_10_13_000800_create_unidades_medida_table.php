<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unidades de medida. Se separan de los productos porque las comparten los
 * insumos: 1 kg de arroz y 1 kg de carne usan la misma unidad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidades_medida', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique();   // kg, lt, und, dz
            $table->string('nombre', 40);
            $table->string('plural', 40)->nullable();
            $table->unsignedSmallInteger('decimales')->default(2);
            $table->boolean('permite_fraccion')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidades_medida');
    }
};
