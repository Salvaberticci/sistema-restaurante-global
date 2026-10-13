<?php

namespace App\Domain\Reservas\Models;

use App\Domain\Reservas\Enums\EstadoPago;
use App\Domain\Reservas\Enums\EstadoReserva;
use App\Domain\Shared\ValueObjects\Money;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una reserva de evento o de curso.
 *
 * El total y el pagado son columnas, no cálculos de la vista, por una razón
 * concreta: la reserva es una promesa económica con fecha. Si el precio del
 * buffet sube en marzo, la reserva de diciembre tiene que seguir diciendo lo
 * que el cliente aceptó en enero.
 *
 * @property int $id
 * @property string $tipo
 * @property string $nombre
 * @property string $fecha_inicio
 * @property string|null $fecha_fin
 * @property string $hora_inicio
 * @property int $personas
 * @property int|null $cupo_maximo
 * @property string $precio_unitario
 * @property string $total
 * @property string $pagado
 * @property EstadoPago $estado_pago
 * @property EstadoReserva $estado
 * @property string $cliente_nombre
 * @property Carbon $fecha_inicio
 * @property Carbon|null $fecha_fin
 * @property Carbon|CarbonImmutable|null $confirmada_at
 */
class Reservacion extends Model
{
    protected $table = 'reservaciones';

    protected $fillable = [
        'tipo', 'nombre', 'descripcion', 'fecha_inicio', 'fecha_fin', 'hora_inicio',
        'personas', 'cupo_maximo', 'precio_unitario', 'total', 'pagado',
        'estado_pago', 'estado', 'cliente_nombre', 'cliente_documento',
        'cliente_telefono', 'observaciones', 'usuario_id', 'confirmada_at',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => 'string',
            'personas' => 'integer',
            'cupo_maximo' => 'integer',
            'precio_unitario' => 'decimal:4',
            'total' => 'decimal:4',
            'pagado' => 'decimal:4',
            'estado_pago' => EstadoPago::class,
            'estado' => EstadoReserva::class,
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'confirmada_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function esCurso(): bool
    {
        return $this->tipo === 'curso';
    }

    public function esEvento(): bool
    {
        return $this->tipo === 'evento';
    }

    /**
     * Invariante I8: la reserva no se confirma sin el pago completo.
     *
     * La tabla lo garantiza con un CHECK, pero el servicio que confirma vuelve a
     * preguntarlo. Son dos capas a propósito: el CHECK protege contra un `UPDATE`
     * escrito a mano en la consola, y esto protege contra un forgot de validarlo
     * al escribir código nuevo.
     */
    public function estaPagada(): bool
    {
        return $this->estado_pago === EstadoPago::Pagado;
    }

    public function saldo(): Money
    {
        $total = (float) $this->total;
        $pagado = (float) $this->pagado;

        return Money::deDolares((string) round(max(0, $total - $pagado), 2));
    }

    /** Fracción pagada, en porcentaje. */
    public function avancePago(): int
    {
        $total = (float) $this->total;

        if ($total <= 0) {
            return 100;
        }

        return (int) min(100, round((float) $this->pagado / $total * 100));
    }

    /** Un curso se paga completo al inscribirse; un evento, por persona. */
    public function precioUnitarioEfectivo(): Money
    {
        return Money::deDolares((string) round((float) $this->precio_unitario, 2));
    }

    public function quedanCupos(): ?int
    {
        if ($this->cupo_maximo === null) {
            return null;
        }

        return max(0, $this->cupo_maximo - $this->personas);
    }
}
