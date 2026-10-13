<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sucursales. Todo documento fiscal, toda orden y todo movimiento de inventario
 * cuelga de una sucursal: el demo tiene dos, y el POS cambia entre ellas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sucursales', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->string('codigo', 20)->unique();
            $table->string('direccion', 255)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('rif', 20)->nullable();
            $table->string('ciudad', 80)->nullable();
            $table->string('estado', 60)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sucursales');
    }
};
