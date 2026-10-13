<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Productos: lo que el mesero pide y el cajero cobra.
 *
 * El precio está en dólares y es el precio de VENTA al público, con el IVA
 * incluido, como aparece en la carta. La alícuota no se guarda como número: se
 * guarda el CÓDIGO (`IVA_G`, `IVA_E`) y el número sale de la tabla de vigencias.
 * Es la invariante I1 aplicada al catálogo.
 *
 * `precio_costo` lo llena el BOM (spec 090). Por ahora es nullable para poder
 * cargar la carta sin inventar costos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();

            $table->string('codigo', 30)->unique();
            $table->string('nombre', 120);
            $table->string('descripcion', 500)->nullable();

            $table->foreignId('categoria_id')->nullable();
            $table->foreignId('unidad_medida_id')->nullable();
            $table->foreignId('estacion_cocina_id')->nullable();

            $table->decimal('precio_venta', 18, 4);
            $table->decimal('precio_costo', 18, 4)->nullable();

            $table->string('alicuota_codigo', 20)->default('IVA_G');

            $table->boolean('requiere_preparacion')->default(true);
            $table->boolean('es_bebida')->default(false);
            $table->boolean('es_insumo')->default(false);

            $table->string('imagen', 255)->nullable();
            $table->boolean('disponible')->default(true);
            $table->boolean('mostrar_menu_digital')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);

            $table->timestamps();

            $table->index(['categoria_id', 'disponible']);
            $table->index('disponible');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
