<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perfil del personal: documento, teléfono y si está activo.
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
            $table->boolean('activo')->default(true)->after('telefono');
            $table->timestamp('ultimo_acceso')->nullable();
            $table->softDeletes();

            $table->index('cedula');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['cedula']);
            $table->dropColumn(['cedula', 'telefono', 'activo', 'ultimo_acceso', 'deleted_at']);
        });
    }
};
