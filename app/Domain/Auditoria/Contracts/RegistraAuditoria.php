<?php

namespace App\Domain\Auditoria\Contracts;

use App\Domain\Auditoria\Models\Auditoria;

/**
 * Invariante I6 de AGENTS.md. Lo implementa el registro en base de datos; los
 * tests pueden sustituirlo por un espía sin tocar el código que audita.
 */
interface RegistraAuditoria
{
    /**
     * @param  string  $accion  'producto.precio_actualizado'
     * @param  string  $entidad  Clase del modelo, FQCN
     * @param  array<string, mixed>  $antes
     * @param  array<string, mixed>  $despues
     */
    public function registrar(
        string $accion,
        string $entidad,
        int|string $entidadId,
        array $antes = [],
        array $despues = [],
        ?int $usuarioId = null,
        array $contexto = [],
    ): Auditoria;

    /** Solo los campos que cambiaron. Evita guardar ruido en la bitácora. */
    public function diferencias(array $antes, array $despues): array;
}
