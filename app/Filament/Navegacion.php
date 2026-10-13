<?php

namespace App\Filament;

/**
 * Los grupos del menú lateral, uno por módulo del sistema.
 *
 * La navegación se declara acá y en ningún otro archivo. Un módulo nuevo es una
 * entrada más: no hay que tocar el panel ni los recursos para que aparezca en
 * el menú.
 *
 * Cada grupo lleva un ícono y un orden pensado para el recorrido del día: lo
 * que se usa a diario arriba, la configuración al final.
 */
final class Navegacion
{
    public const OPERACION = 'Operación';

    public const CATALOGOS = 'Catálogos';

    public const FINANZAS = 'Caja y facturación';

    public const GESTION = 'Gestión';

    public const ADMINISTRACION = 'Administración';

    /**
     * @return array<int, array{nombre: string, icono: string}>
     */
    public static function grupos(): array
    {
        return [
            ['nombre' => self::OPERACION, 'icono' => 'heroicon-o-signal'],
            ['nombre' => self::CATALOGOS, 'icono' => 'heroicon-o-book-open'],
            ['nombre' => self::FINANZAS, 'icono' => 'heroicon-o-banknotes'],
            ['nombre' => self::GESTION, 'icono' => 'heroicon-o-chart-bar'],
            ['nombre' => self::ADMINISTRACION, 'icono' => 'heroicon-o-cog-6-tooth'],
        ];
    }
}
