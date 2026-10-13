<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de auditoría. Invariante I6 de AGENTS.md: precio, tasa de cambio,
 * descuento, propina, anulación, cambio de rol y emisión de Reporte Z pasan por
 * acá.
 *
 * Es append-only por diseño: no tiene `updated_at` y el modelo bloquea el
 * `update` y el `delete`. Por eso no hay índice único: varias entradas del mismo
 * actor sobre la misma entidad son el caso normal, no el error.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->nullable();
            $table->string('actor_nombre', 120)->nullable();
            $table->string('accion', 120);

            /*
             * `entidad` y `entidad_id` están en un índice compuesto, y ese es el lugar donde
             * se cuela la trampa 1071 del servidor: con utf8mb4 cada carácter ocupa 4 bytes
             * y el límite del índice es 1000 bytes por columna. Con 191 + 64 = 255
             * caracteres el índice pide 1020 bytes y la migración falla.
             *
             * Con 120 + 64 = 184 son 736 bytes, y 120 caracteres sobran para el nombre de
             * una clase. El nombre de la entidad va en la columna, no en el índice.
             */
            $table->string('entidad', 120);
            $table->string('entidad_id', 64);

            $table->json('antes')->nullable();
            $table->json('despues')->nullable();
            $table->json('contexto')->nullable();

            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['entidad', 'entidad_id']);
            $table->index(['accion', 'created_at']);
            $table->index('usuario_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias');
    }
};
