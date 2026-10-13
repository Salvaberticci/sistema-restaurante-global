<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de tasas de cambio.
 *
 * No se guarda "la tasa de hoy" en una sola fila que se va pisando: se guarda
 * cada tasa con la vigencia que tenía. Es la misma razón por la que las alícuotas
 * se consultan por fecha (invariante I1) y por la que la tasa queda congelada en
 * cada documento: un documento viejo tiene que poder convertirse con la tasa de
 * ese día, aunque hoy la tasa sea otra.
 *
 * El rango `vigente_desde` / `vigente_hasta` solapa a propósito entre fuentes: la
 * BCV publica un valor al día, pero un documento emitido a las 10:00 usa la tasa
 * de ese día aunque a la 15:00 se publique otra. Por eso la consulta pide la tasa
 * vigente **a la fecha del documento**, no "la última".
 */
return new class extends Migration
{
    protected $table = 'tasas_cambio';

    public function up(): void
    {
        Schema::create($this->table, function (Blueprint $table) {
            $table->id();
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();

            $table->decimal('usd', 18, 4);
            $table->decimal('eur', 18, 4)->nullable();

            $table->string('fuente', 60)->default('bcv');
            $table->foreignId('usuario_id')->nullable();

            // Una tasa que nadie confirmó se muestra, pero no se usa para emitir.
            // La diferencia importa: el IGTF y la conversión de un documento
            // necesitan un número del que estar seguros.
            $table->boolean('confirmada')->default(false);

            $table->string('observacion', 255)->nullable();
            $table->timestamps();

            $table->index(['vigente_desde', 'vigente_hasta']);
            $table->index('fuente');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table);
    }
};
