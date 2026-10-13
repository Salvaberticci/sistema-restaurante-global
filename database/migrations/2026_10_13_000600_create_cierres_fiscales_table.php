<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reportes X y Z, y arqueos de caja.
 *
 * Son dos cosas distintas y el cliente las confunde todo el tiempo:
 *
 *  - El Reporte X y el Z son obligaciones del emisor fiscal. El Z graba en la
 *    memoria fiscal y resetea el turno.
 *  - El arqueo de caja es del restaurante: compares el efectivo contado con el
 *    esperado y te dice cuánto falta o sobra. No va al SENIAT.
 *
 * El arqueo va en esta tabla y no en otra porque los dos se consultan juntos al
 * cierre del turno del cajero.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cierres_fiscales', function (Blueprint $table) {
            $table->id();

            $table->string('tipo', 10);
            $table->date('fecha_fiscal');
            $table->foreignId('sucursal_id');
            $table->foreignId('cajero_id')->nullable();
            $table->foreignId('usuario_id')->nullable();

            $table->string('estado', 40)->default('emitido');
            $table->unsignedInteger('cantidad_documentos')->default(0);
            $table->decimal('total', 18, 4)->default(0);

            $table->unsignedInteger('contador_z')->nullable();
            $table->string('serial', 40)->nullable();
            $table->boolean('afecto_memoria_fiscal')->default(false);

            $table->decimal('esperado_efectivo', 18, 4)->nullable();
            $table->decimal('contado_efectivo', 18, 4)->nullable();
            $table->decimal('diferencia', 18, 4)->nullable();
            $table->text('observaciones')->nullable();

            $table->json('payload_emisor')->nullable();
            $table->timestamp('emitido_en')->nullable();
            $table->timestamps();

            $table->index(['sucursal_id', 'fecha_fiscal', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cierres_fiscales');
    }
};
