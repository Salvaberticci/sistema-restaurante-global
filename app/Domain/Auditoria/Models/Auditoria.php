<?php

namespace App\Domain\Auditoria\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Entrada de bitácora. Invariante I3: append-only, como los documentos fiscales.
 *
 * `created_at` sin `updated_at` no es descuido, es la forma de que la base diga
 * que esta fila no se toca nunca.
 *
 * @property int $id
 * @property int|null $usuario_id
 * @property string|null $actor_nombre
 * @property string $accion
 * @property string $entidad
 * @property string $entidad_id
 * @property array<string, mixed>|null $antes
 * @property array<string, mixed>|null $despues
 * @property array<string, mixed>|null $contexto
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon|null $created_at
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

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * El cambio en una línea, para la tabla.
     *
     * La pregunta que se le hace a una bitácora es "¿qué pasó?", y
     * `precio_venta: 15 → 18` la responde. Sacar solo el nombre del campo obliga
     * a abrir cada entrada una por una para enterarse de algo.
     */
    public function resumen(): ?string
    {
        $antes = $this->antes ?? [];
        $despues = $this->despues ?? [];

        if ($antes === [] && $despues === []) {
            return null;
        }

        $partes = [];

        foreach ($despues as $campo => $valor) {
            $previo = $antes[$campo] ?? null;

            if ($previo === $valor) {
                continue;
            }

            $partes[] = $campo.': '.$this->legible($previo).' → '.$this->legible($valor);
        }

        return $partes === [] ? null : implode(', ', array_slice($partes, 0, 3));
    }

    private function legible(mixed $valor): string
    {
        return match (true) {
            $valor === null => '—',
            is_bool($valor) => $valor ? 'sí' : 'no',
            is_scalar($valor) => (string) $valor,
            default => json_encode($valor, JSON_UNESCAPED_UNICODE) ?: '—',
        };
    }
}
