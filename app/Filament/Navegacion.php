<?php

namespace App\Filament;

use Filament\Navigation\NavigationGroup;

/**
 * Los grupos del menú lateral, uno por módulo del sistema.
 *
 * La navegación se declara acá y en ningún otro archivo. Un módulo nuevo es una
 * entrada más: no hay que tocar el panel ni los recursos para que aparezca en el
 * menú.
 *
 * El orden es el del recorrido del día: lo que se usa a diario arriba, la
 * configuración al final. En Filament 3 el orden es el del arreglo —de eso se
 * encarga `navigationGroups()`—, por eso no hay números de orden acá y no se
 * mezclan con los `navigationSort` de cada recurso, que ordenan dentro del grupo.
 */
final class Navegacion
{
    public const OPERACION = 'Operación';

    public const FINANZAS = 'Caja y facturación';

    public const GESTION = 'Gestión';

    public const CATALOGOS = 'Catálogos';

    public const ADMINISTRACION = 'Administración';

    /**
     * Los grupos, en el orden en que se muestran.
     *
     * Sin ícono a propósito: Filament 3.3 lanza una excepción si un grupo tiene
     * ícono y alguno de sus ítems también. Los recursos ya lo traen —el ícono del
     * ítem es el que describe mejor a cada módulo—, así que el del grupo sobra.
     *
     * @return array<int, NavigationGroup>
     */
    public static function grupos(): array
    {
        return [
            NavigationGroup::make(self::OPERACION)->collapsible(false),
            NavigationGroup::make(self::FINANZAS),
            NavigationGroup::make(self::GESTION),
            NavigationGroup::make(self::CATALOGOS),
            NavigationGroup::make(self::ADMINISTRACION),
        ];
    }
}
