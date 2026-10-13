<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reservaciones de eventos y cursos.
 *
 * `tipo` separa un evento de un curso porque las reglas son distintas: un curso
 * tiene cupo y se paga completo al inscribirse, un evento seCotiza por persona.
 * En los dos casos el pago va por adelantado al 100% (invariante I8), y por eso
 * `estado_pago` es una columna de primer nivel y no un detalle de la cuenta.
 *
 * El CHECK de `estado_pago` está a nivel de base y no solo en el enum de PHP a
 * propósito: es la regla que el sistema no puede permitir que alguien rompa por
 * error. Una reserva sin pago completo tiene que ser imposible de guardar.
 */
return new class extends Migration
{
    protected $table = 'reservaciones';

    public function up(): void
    {
        Schema::create($this->table, function (Blueprint $table) {
            $table->id();

            $table->string('tipo', 20)->default('evento');   // evento | curso
            $table->string('nombre', 120);
            $table->text('descripcion')->nullable();

            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->time('hora_inicio')->default('18:00');

            $table->unsignedSmallInteger('personas')->default(1);
            $table->unsignedSmallInteger('cupo_maximo')->nullable();

            // Los eventos se cobran por persona; el curso, completo. El importe
            // total se guarda ya calculado para que la pantalla no tenga que
            // recomputar y arriesgarse a mostrar otra cosa.
            $table->decimal('precio_unitario', 18, 4)->default(0);
            $table->decimal('total', 18, 4)->default(0);
            $table->decimal('pagado', 18, 4)->default(0);

            // pendiente | parcial | pagado. El CHECK de abajo es el que hace
            // cumplir la invariante I8 en la base, no solo en el código.
            $table->string('estado_pago', 20)->default('pendiente');
            $table->string('estado', 20)->default('solicitada'); // solicitada | confirmada | cancelada | realizada

            $table->string('cliente_nombre', 120);
            $table->string('cliente_documento', 30)->nullable();
            $table->string('cliente_telefono', 30)->nullable();

            $table->text('observaciones')->nullable();
            $table->foreignId('usuario_id')->nullable();

            $table->timestamp('confirmada_at')->nullable();
            $table->timestamps();

            $table->index(['tipo', 'estado']);
            $table->index('fecha_inicio');
            $table->index('estado_pago');
        });

        /*
         * Invariante I8 a nivel de motor: no existe una reserva confirmada cuyo
         * pago no esté completo.
         *
         * Va como `ALTER TABLE` porque el constructor de esquema de Laravel no
         * expone restricciones CHECK: hay que escribir el DDL. MySQL 8 y MariaDB
         * 10.2+ lo evalúan, así que la base de verdad rechaza el INSERT — no es
         * una convención que el código respeta por buena voluntad.
         */
        DB::statement(
            "ALTER TABLE {$this->table}
             ADD CONSTRAINT reservaciones_confirmada_pagada_check
             CHECK (estado <> 'confirmada' OR (estado_pago = 'pagado' AND pagado >= total))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table);
    }
};
