<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parámetros de configuración del sistema.
 *
 * Es lo que hace cumplir la invariante I1 sin escribir una tasa en el código:
 * los porcentajes de IGTF, las alícuotas de IVA y las políticas comerciales son
 * filas de esta tabla con vigencia, editables desde Filament y auditadas.
 *
 * El valor va en json porque no todos los parámetros son escalares: hay
 * booleanos, listas y estructuras.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametros', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 120)->unique();
            $table->json('valor');
            $table->string('descripcion', 255)->nullable();
            $table->string('grupo', 60)->default('general');
            $table->foreignId('usuario_id')->nullable();
            $table->timestamps();

            $table->index('grupo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametros');
    }
};
