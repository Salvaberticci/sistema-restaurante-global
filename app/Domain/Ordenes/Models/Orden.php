<?php

namespace App\Domain\Ordenes\Models;

use App\Domain\Facturacion\Models\DocumentoFiscal;
use App\Domain\Ordenes\Enums\Canal;
use App\Domain\Ordenes\Enums\EstadoOrden;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lo que pidió una mesa y terminó en cuenta.
 *
 * Los importes son snapshots: guardan lo que se cobró, no lo que el catálogo
 * diga hoy. Si mañana suben el pabellón, esta orden sigue valiendo lo que valía
 * ayer porque ese número se le cobró a alguien.
 */
/**
 * @property string $numero
 * @property CarbonImmutable $abierta_at
 * @property CarbonImmutable|null $cerrada_at
 * @property CarbonImmutable|null $cobrada_at
 * @property string $estado
 * @property Canal $canal
 */
class Orden extends Model
{
    protected $table = 'ordenes';

    protected $fillable = [
        'numero', 'canal', 'mesa_id', 'mesero_id', 'cajero_id', 'estado', 'comensales',
        'metodo_pago', 'subtotal', 'descuentos', 'propina', 'total', 'moneda',
        'tasa_cambio', 'total_bolivares', 'documento_fiscal_id', 'observaciones',
        'abierta_at', 'cerrada_at', 'cobrada_at',
    ];

    protected function casts(): array
    {
        return [
            'canal' => Canal::class,
            'estado' => EstadoOrden::class,
            'comensales' => 'integer',
            'subtotal' => 'decimal:4',
            'descuentos' => 'decimal:4',
            'propina' => 'decimal:4',
            'total' => 'decimal:4',
            'tasa_cambio' => 'decimal:4',
            'total_bolivares' => 'decimal:4',
            'abierta_at' => 'datetime',
            'cerrada_at' => 'datetime',
            'cobrada_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrdenItem::class, 'orden_id');
    }

    public function mesa(): BelongsTo
    {
        return $this->belongsTo(Mesa::class);
    }

    public function mesero(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mesero_id');
    }

    public function cajero(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cajero_id');
    }

    public function documentoFiscal(): BelongsTo
    {
        return $this->belongsTo(DocumentoFiscal::class);
    }

    /** Solo los ítems que se cobraron. Un ítem cortado no entra en la cuenta. */
    public function itemsCobrados(): HasMany
    {
        return $this->items()->where('estado', '!=', 'cortado');
    }

    public function scopeCobradas($query)
    {
        return $query->where('estado', EstadoOrden::Cobrada->value);
    }

    public function scopeEntreFechas($query, $desde, $hasta)
    {
        return $query->whereBetween('abierta_at', [$desde, $hasta]);
    }

    public function duracionMinutos(): ?int
    {
        if ($this->cerrada_at === null) {
            return null;
        }

        return (int) $this->abierta_at->diffInMinutes($this->cerrada_at);
    }
}
