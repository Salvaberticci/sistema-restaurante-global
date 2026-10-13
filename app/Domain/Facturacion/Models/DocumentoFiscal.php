<?php

namespace App\Domain\Facturacion\Models;

use App\Domain\Ordenes\Models\Orden;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Documento fiscal emitido. Invariante I3: append-only.
 *
 * El modelo bloquea el `update` de los campos que no se tocan nunca y no deja
 * borrar nada. El estado sí avanza (de `queued` a `issued`), porque el
 * documento tiene que refleja su ciclo de vida real.
 *
 * @property int $id
 * @property string $tipo
 * @property string $estado
 * @property string $serie
 * @property int|null $numero
 * @property string $idempotency_key
 * @property string|null $numero_fiscal
 * @property string|null $serial
 * @property int|null $contador
 * @property string|null $hash
 * @property string $moneda
 * @property string|null $fecha_fiscal
 * @property int $intentos
 * @property array<string, mixed>|null $iva_por_alicuota
 * @property array<string, mixed>|null $igtf_por_perfil
 * @property array<int, mixed>|null $pagos
 * @property array<string, mixed>|null $cliente_snapshot
 * @property array<int, mixed>|null $ordenes_origen
 * @property array<string, mixed>|null $documento_payload
 * @property array<string, mixed>|null $resultado_emisor
 * @property Carbon|null $fecha
 * @property Carbon|null $emitido_en
 */
class DocumentoFiscal extends Model
{
    protected $table = 'documentos_fiscales';

    protected $fillable = [
        'tipo', 'estado', 'serie', 'numero', 'idempotency_key',
        'documento_padre_id', 'orden_id', 'cliente_id', 'usuario_id',
        'numero_fiscal', 'serial', 'contador', 'hash',
        'moneda', 'tasa_cambio', 'base_imponible', 'total_impuestos',
        'total_igtf', 'total_propina', 'total_descuentos', 'total',
        'iva_por_alicuota', 'igtf_por_perfil', 'pagos', 'cliente_snapshot',
        'ordenes_origen', 'documento_payload', 'resultado_emisor',
        'fecha_fiscal', 'intentos', 'ultimo_error', 'emitido_en',
    ];

    protected function casts(): array
    {
        return [
            'tasa_cambio' => 'decimal:4',
            'base_imponible' => 'decimal:4',
            'total_impuestos' => 'decimal:4',
            'total_igtf' => 'decimal:4',
            'total_propina' => 'decimal:4',
            'total_descuentos' => 'decimal:4',
            'total' => 'decimal:4',
            'iva_por_alicuota' => 'array',
            'igtf_por_perfil' => 'array',
            'pagos' => 'array',
            'cliente_snapshot' => 'array',
            'ordenes_origen' => 'array',
            'documento_payload' => 'array',
            'resultado_emisor' => 'array',
            'fecha_fiscal' => 'date',
            'emitido_en' => 'datetime',
            'intentos' => 'integer',
        ];
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(Orden::class, 'orden_id');
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'documento_padre_id');
    }

    /**
     * Quién emitió el documento.
     *
     * Queda registrado aunque el usuario después se borre de la base: por eso
     * además existe `numero_fiscal` y `serial`, que son del emisor. Borrar al
     * empleado no puede dejar un documento sin origen.
     *
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeEmitidos($query)
    {
        return $query->where('estado', 'issued');
    }

    public function fueAnulado(): bool
    {
        return in_array($this->estado, ['anulado', 'nota_emitida'], true);
    }
}
