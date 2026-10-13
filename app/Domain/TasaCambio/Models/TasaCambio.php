<?php

namespace App\Domain\TasaCambio\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una tasa de cambio, con la vigencia que tenía.
 *
 * El historial es append-only por la misma razón que los documentos fiscales:
 * la tasa es la evidencia de cuánto vale el dólar el día que se cobró. Corregir
 * una tasa pasada cambia lo que significan mil cuentas ya emitidas.
 *
 * @property int $id
 * @property Carbon $vigente_desde
 * @property Carbon|null $vigente_hasta
 * @property string $usd
 * @property string|null $eur
 * @property string $fuente
 * @property bool $confirmada
 * @property string|null $observacion
 */
class TasaCambio extends Model
{
    protected $table = 'tasas_cambio';

    protected $fillable = [
        'vigente_desde', 'vigente_hasta', 'usd', 'eur',
        'fuente', 'usuario_id', 'confirmada', 'observacion',
    ];

    protected function casts(): array
    {
        return [
            'usd' => 'decimal:4',
            'eur' => 'decimal:4',
            'confirmada' => 'boolean',
            'vigente_desde' => 'date',
            'vigente_hasta' => 'date',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Si esta tasa cubre la fecha dada.
     *
     * `vigente_hasta` en `null` significa "sigue vigente". Una tasa diaria trae
     * `vigente_hasta` igual a `vigente_desde`.
     */
    public function cubre(string $fecha): bool
    {
        if ($this->vigente_desde->toDateString() > $fecha) {
            return false;
        }

        return $this->vigente_hasta === null
            || $this->vigente_hasta->toDateString() >= $fecha;
    }
}
