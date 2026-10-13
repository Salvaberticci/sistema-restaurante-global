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

            $table->string('entidad', 191);
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
