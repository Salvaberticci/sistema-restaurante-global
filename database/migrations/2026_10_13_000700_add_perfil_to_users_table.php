<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perfil del personal: documento, teléfono y sucursal de trabajo.
 *
 * Se agrega como migración nueva y no editando la de usuarios de Laravel,
 * porque AGENTS.md dice que una migración ya aplicada en el servidor no se
 * toca: se agrega otra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('cedula', 20)->nullable()->after('email');
            $table->string('telefono', 30)->nullable()->after('cedula');
            $table->foreignId('sucursal_id')->nullable()->after('telefono');
            $table->boolean('activo')->default(true)->after('sucursal_id');
            $table->timestamp('ultimo_acceso')->nullable();
            $table->softDeletes();

            $table->index('sucursal_id');
            $table->index('cedula');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['sucursal_id']);
            $table->dropIndex(['cedula']);
            $table->dropColumn(['cedula', 'telefono', 'sucursal_id', 'activo', 'ultimo_acceso', 'deleted_at']);
        });
    }
};