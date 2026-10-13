<?php

namespace App\Domain\Auditoria\Services;

use App\Domain\Auditoria\Contracts\RegistraAuditoria;
use App\Domain\Auditoria\Models\Auditoria;
use App\Models\User as UsuarioPorId;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

/**
 * Registro de auditoría contra la base de datos.
 *
 * La entrada se escribe dentro de la transacción abierta por quien cambió algo.
 * Si ese cambio falla, no queda la entrada: una auditoría de algo que no pasó es
 * peor que no tener auditoría, porque miente.
 */
final class RegistraAuditoriaBd implements RegistraAuditoria
{
    public function registrar(
        string $accion,
        string $entidad,
        int|string $entidadId,
        array $antes = [],
        array $despues = [],
        ?int $usuarioId = null,
        array $contexto = [],
    ): Auditoria {
        $actor = $this->actor($usuarioId);
        $cambios = $this->diferencias($antes, $despues);
        $peticion = request();

        return Auditoria::create([
            'usuario_id' => $actor === null ? null : (int) $actor->getAuthIdentifier(),
            'actor_nombre' => $actor?->getAttribute('name') ?? 'sistema',
            'accion' => $accion,
            'entidad' => $entidad,
            'entidad_id' => (string) $entidadId,
            'antes' => $antes === [] ? null : $cambios['antes'],
            'despues' => $despues === [] ? null : $cambios['despues'],
            'contexto' => $contexto === [] ? null : $contexto,
            'ip' => $peticion->ip(),
            'user_agent' => mb_substr((string) $peticion->userAgent(), 0, 255),
        ]);
    }

    /**
     * Solo los campos que de verdad cambiaron. Guardar el snapshot completo en
     * cada entrada infla la bitácora y la vuelve inútil para leerla.
     *
     * @return array{antes: array<string, mixed>|null, despues: array<string, mixed>|null}
     */
    public function diferencias(array $antes, array $despues): array
    {
        if ($antes === [] || $despues === []) {
            return ['antes' => $antes ?: null, 'despues' => $despues ?: null];
        }

        $camposAntes = [];
        $camposDespues = [];

        foreach (array_unique([...array_keys($antes), ...array_keys($despues)]) as $campo) {
            $valorAntes = $antes[$campo] ?? null;
            $valorDespues = $despues[$campo] ?? null;

            if ($valorAntes === $valorDespues) {
                continue;
            }

            $camposAntes[$campo] = $valorAntes;
            $camposDespues[$campo] = $valorDespues;
        }

        return [
            'antes' => $camposAntes ?: null,
            'despues' => $camposDespues ?: null,
        ];
    }

    private function actor(?int $usuarioId): ?Authenticatable
    {
        if ($usuarioId !== null) {
            return UsuarioPorId::find($usuarioId);
        }

        return Auth::user();
    }
}
