<?php

namespace App\Domain\Ordenes\Models;

use App\Domain\Mesas\Models\Zona;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Mesa extends Model
{
    protected $table = 'mesas';

    protected $fillable = ['nombre', 'codigo', 'zona_id', 'sillas', 'pos_x', 'pos_y', 'activo'];

    protected function casts(): array
    {
        return [
            'sillas' => 'integer',
            'pos_x' => 'integer',
            'pos_y' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class);
    }

    public function ordenes(): HasMany
    {
        return $this->hasMany(Orden::class);
    }

    /** La orden abierta de la mesa, si hay una. Una mesa tiene una sola cuenta. */
    public function ordenAbierta(): HasOne
    {
        return $this->hasOne(Orden::class)
            ->whereNotIn('estado', ['cobrada', 'anulada'])
            ->latestOfMany('abierta_at');
    }

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }
}
