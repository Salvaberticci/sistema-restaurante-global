<?php

namespace App\Domain\Auditoria\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Entrada de bitácora. Invariante I3: append-only, como los documentos fiscales.
 *
 * `created_at` sin `updated_at` no es descuido, es la forma de que la base diga
 * que esta fila no se toca nunca.
 */
class Auditoria extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'usuario_id', 'actor_nombre', 'accion', 'entidad', 'entidad_id',
        'antes', 'despues', 'contexto', 'ip', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'antes' => 'array',
            'despues' => 'array',
            'contexto' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** Impide que algo del código corrija una auditoría pasada. */
    public static function boot(): void
    {
        parent::boot();

        static::updating(fn () => throw new \LogicException(
            'Las entradas de auditoría no se modifican: se agrega una nueva.'
        ));

        static::deleting(fn () => throw new \LogicException(
            'Las entradas de auditoría no se borran.'
        ));
    }
}
