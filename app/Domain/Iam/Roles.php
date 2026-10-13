<?php

namespace App\Domain\Iam;

/**
 * Los cinco roles del restaurante y qué puede hacer cada uno.
 *
 * La matriz se declara acá, como datos. Un rol nuevo es una entrada más, no una
 * cadena de `if` repartida por los controladores.
 *
 * Cada rol tiene un alcance distinto y esa diferencia es el punto:
 *
 *  - El mesero no ve todas las órdenes: ve las suyas.
 *  - La cocina no ve precios. Cobra menos, hace menos, no se equivoca.
 *  - El cajero cobra y cierra, pero no cambia un precio de venta.
 *  - El gerente ve los números y los inventarios, pero no opera la caja.
 */
final class Roles
{
    public const ADMINISTRADOR = 'administrador';

    public const GERENTE = 'gerente';

    public const CAJERO = 'cajero';

    public const MESERO = 'mesero';

    public const COCINA = 'cocina';

    /**
     * @return array<string, array{descripcion: string, permisos: list<string>}>
     */
    public static function todos(): array
    {
        return [
            self::ADMINISTRADOR => [
                'descripcion' => 'Acceso total. Configura el sistema y resuelve lo que nadie más puede.',
                'permisos' => Permisos::todos(),
            ],

            self::GERENTE => [
                'descripcion' => 'Ve los números, administra inventario, reservas y catálogo. No opera la caja ni emite documentos fiscales.',
                'permisos' => [
                    Permisos::CATALOGOS_VER, Permisos::CATALOGOS_EDITAR,
                    Permisos::USUARIOS_VER,
                    Permisos::MESAS_VER, Permisos::MESAS_EDITAR,
                    Permisos::ORDENES_VER_TODAS,
                    Permisos::INVENTARIO_VER, Permisos::INVENTARIO_AJUSTAR, Permisos::INVENTARIO_RECETAS,
                    Permisos::RESERVAS_VER, Permisos::RESERVAS_EDITAR,
                    Permisos::VENTAS_VER, Permisos::VENTAS_REPORTES,
                    Permisos::FACTURACION_VER,
                    Permisos::PARAMETROS_VER,
                    Permisos::AUDITORIA_VER,
                ],
            ],

            self::CAJERO => [
                'descripcion' => 'Cobra, emite documentos fiscales y cierra el turno. No cambia precios ni ve la utilidad.',
                'permisos' => [
                    Permisos::CATALOGOS_VER,
                    Permisos::MESAS_VER,
                    Permisos::ORDENES_VER_TODAS,
                    Permisos::POS_USAR,
                    Permisos::CAJA_REPORTE_X, Permisos::CAJA_REPORTE_Z,
                    Permisos::CAJA_ARQUEO, Permisos::CAJA_REEMBOLSO,
                    Permisos::VENTAS_VER,
                    Permisos::FACTURACION_VER, Permisos::FACTURACION_EMITIR, Permisos::FACTURACION_NOTA,
                    Permisos::PARAMETROS_VER,
                ],
            ],

            self::MESERO => [
                'descripcion' => 'Toma comandas en su tablet y lleva la cuenta. Solo ve sus propias órdenes.',
                'permisos' => [
                    Permisos::CATALOGOS_VER,
                    Permisos::MESAS_VER,
                    Permisos::ORDENES_DESCUENTO,
                    Permisos::RESERVAS_VER,
                ],
            ],

            self::COCINA => [
                'descripcion' => 'Pantalla de comandas únicamente. No ve precios ni información de caja.',
                'permisos' => [
                    Permisos::COCINA_VER,
                    Permisos::COCINA_CORTAR_ITEM,
                ],
            ],
        ];
    }

    /** @return list<string> */
    public static function nombres(): array
    {
        return array_keys(self::todos());
    }
}
