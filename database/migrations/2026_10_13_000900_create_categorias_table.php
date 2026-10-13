<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categorías del menú. El orden es el orden en que salen en la carta y en la
 * pantalla del mesero.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80);
            $table->string('slug', 80)->unique();
            $table->string('descripcion', 255)->nullable();
            $table->string('icono', 60)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('mostrar_menu_digital')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['activo', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias');
    }
};
