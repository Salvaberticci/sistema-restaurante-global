<?php

namespace App\Models;

use App\Domain\Mesas\Models\Sucursal;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Persona del equipo. Un mismo usuario puede tener más de un rol: un
 * administrador que también cobra en caja es normal en un restaurante chico.
 *
 * El nombre de la columna sigue siendo `name` porque es el que trae Laravel, y
 * se muestra como nombre completo en toda la interfaz.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'telefono',
        'cedula',
        'sucursal_id',
        'activo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * El POS y la cocina solo aceptan gente activa. Un usuario desactivado se
     * queda con su historial y no puede operar.
     */
    public function puedeOperar(): bool
    {
        return (bool) $this->activo;
    }

    public function iniciales(): string
    {
        $partes = preg_split('/\s+/u', trim($this->name)) ?: [];

        $primera = mb_substr($partes[0] ?? '', 0, 1);
        $segunda = count($partes) > 1 ? mb_substr(end($partes), 0, 1) : '';

        return mb_strtoupper($primera.$segunda);
    }
}
