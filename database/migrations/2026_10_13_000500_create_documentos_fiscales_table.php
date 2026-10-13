<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documentos fiscales. Invariante I3: append-only.
 *
 * Lo que ve el sistema y lo que responde el emisor se guardan por separado:
 * el número fiscal y el serial son los del emisor, y si no coincidieran con lo
 * que el sistema calculó, la conciliación entre ambos lo detecta.
 *
 * `idempotency_key` es única. Es lo que impide que un reintento de cola emita
 * dos veces la misma factura (invariante I5).
 *
 * Las columnas fiscales completas llegan con la spec 070; aquí va el esqueleto
 * que necesita el driver para poder abrir, cerrar y reintentar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_fiscales', function (Blueprint $table) {
            $table->id();

            $table->string('tipo', 40);
            $table->string('estado', 40)->default('draft');
            $table->string('serie', 10);
            $table->unsignedBigInteger('numero')->nullable();

            $table->string('idempotency_key', 191)->unique();

            $table->foreignId('documento_padre_id')->nullable();
            $table->foreignId('orden_id')->nullable();
            $table->foreignId('cliente_id')->nullable();
            $table->foreignId('usuario_id')->nullable();

            $table->string('numero_fiscal', 40)->nullable();
            $table->string('serial', 40)->nullable();
            $table->unsignedBigInteger('contador')->nullable();
            $table->string('hash', 80)->nullable();

            $table->string('moneda', 3)->default('USD');
            $table->decimal('tasa_cambio', 18, 4)->nullable();
            $table->decimal('base_imponible', 18, 4)->nullable();
            $table->decimal('total_impuestos', 18, 4)->nullable();
            $table->decimal('total_igtf', 18, 4)->nullable();
            $table->decimal('total_propina', 18, 4)->nullable();
            $table->decimal('total_descuentos', 18, 4)->nullable();
            $table->decimal('total', 18, 4);

            $table->json('iva_por_alicuota')->nullable();
            $table->json('igtf_por_perfil')->nullable();
            $table->json('pagos')->nullable();
            $table->json('cliente_snapshot')->nullable();
            $table->json('ordenes_origen')->nullable();
            $table->json('documento_payload')->nullable();
            $table->json('resultado_emisor')->nullable();

            $table->date('fecha_fiscal');
            $table->unsignedSmallInteger('intentos')->default(0);
            $table->text('ultimo_error')->nullable();

            $table->timestamp('emitido_en')->nullable();
            $table->timestamps();

            $table->index(['fecha_fiscal', 'tipo']);
            $table->index(['estado', 'created_at']);
            $table->index(['tipo', 'serie', 'numero'], 'documentos_fiscales_numero_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_fiscales');
    }
};
