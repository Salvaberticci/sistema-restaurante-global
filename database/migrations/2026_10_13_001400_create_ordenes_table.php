<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Órdenes: lo que el mesero abrió para una mesa y lo que terminó en cuenta.
 *
 * Una orden NO es una factura. Son tres cosas separadas a propósito:
 *
 *   orden        → lo que pidió la mesa, en la tablet del mesero
 *   cuenta       → la cuenta abierta, lo que se le debe al cliente
 *   documento    → lo que el cajero emitió al cobrar
 *
 * Juntas en una sola tabla, no se puede saber si algo se cobró dos veces, ni
 * separar una comanda de una venta, ni auditar una anulación.
 *
 * `canal` distingue mesa, para llevar y delivery: cambia el servicio, la
 * propina sugerida y qué tan rápido tiene que salir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordenes', function (Blueprint $table) {
            $table->id();

            $table->string('numero', 20)->unique();
            $table->string('canal', 20)->default('mesa');   // mesa | para_llevar | delivery

            $table->foreignId('mesa_id')->nullable();
            $table->foreignId('mesero_id')->nullable();
            $table->foreignId('cajero_id')->nullable();

            $table->string('estado', 30)->default('abierta');
            // abierta → enviada_cocina → servida → requesting_cuenta → cerrada
            // cerrada → cobrada
            // exceptional: anulada

            $table->unsignedSmallInteger('comensales')->nullable();

            /**
             * Cómo se cobró. Es el dato que después descompone el IGTF: solo los
             * pagos en divisa lo generan, y cada modalidad tiene su alícuota.
             * Una orden puede tener varios pagos, pero el predominante alcanza
             * para el gráfico de distribución; el desglose completo vive en el
             * documento fiscal.
             */
            $table->string('metodo_pago', 30)->default('efectivo_usd');

            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('descuentos', 18, 4)->default(0);
            $table->decimal('propina', 18, 4)->default(0);
            $table->decimal('total', 18, 4)->default(0);

            $table->string('moneda', 3)->default('USD');
            $table->decimal('tasa_cambio', 18, 4)->nullable();
            $table->decimal('total_bolivares', 18, 4)->nullable();

            $table->foreignId('documento_fiscal_id')->nullable();
            $table->text('observaciones')->nullable();

            $table->timestamp('abierta_at');
            $table->timestamp('cerrada_at')->nullable();
            $table->timestamp('cobrada_at')->nullable();

            $table->timestamps();

            $table->index(['estado', 'abierta_at']);
            $table->index('abierta_at');
            $table->index('canal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordenes');
    }
};
