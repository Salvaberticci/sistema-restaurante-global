<?php

namespace App\Domain\Iam;

/**
 * Los permisos del sistema y la matriz que dice qué rol tiene cuál.
 *
 * Los permisos viven acá como datos y NO se escriben en los controladores. Un
 * permiso nuevo se agrega a la lista y se reparte entre los roles; ningún `if`
 * de permiso vive en otro archivo.
 *
 * Los nombres van con punto y van por módulo, en español, porque es el idioma
 * del dominio y el que va a leer el usuario al configurar un rol.
 */
final class Permisos
{
    public const CATALOGOS_VER = 'catalogo.ver';

    public const CATALOGOS_EDITAR = 'catalogo.editar';

    public const USUARIOS_VER = 'usuario.ver';

    public const USUARIOS_EDITAR = 'usuario.editar';

    public const ROLES_GESTIONAR = 'rol.gestionar';

    public const MESAS_VER = 'mesa.ver';

    public const MESAS_EDITAR = 'mesa.editar';

    public const ORDENES_VER_TODAS = 'orden.ver_todas';

    public const ORDENES_ANULAR = 'orden.anular';

    public const ORDENES_DESCUENTO = 'orden.descuento';

    public const POS_USAR = 'pos.usar';

    public const CAJA_REPORTE_X = 'caja.reporte_x';

    public const CAJA_REPORTE_Z = 'caja.reporte_z';

    public const CAJA_ARQUEO = 'caja.arqueo';

    public const CAJA_REEMBOLSO = 'caja.reembolso';

    public const COCINA_VER = 'cocina.ver';

    public const COCINA_CORTAR_ITEM = 'cocina.cortar_item';

    public const INVENTARIO_VER = 'inventario.ver';

    public const INVENTARIO_AJUSTAR = 'inventario.ajustar';

    public const INVENTARIO_RECETAS = 'inventario.recetas';

    public const RESERVAS_VER = 'reserva.ver';

    public const RESERVAS_EDITAR = 'reserva.editar';

    public const VENTAS_VER = 'venta.ver';

    public const VENTAS_REPORTES = 'venta.reportes';

    public const PARAMETROS_VER = 'parametro.ver';

    public const PARAMETROS_EDITAR = 'parametro.editar';

    public const AUDITORIA_VER = 'auditoria.ver';

    public const FACTURACION_VER = 'facturacion.ver';

    public const FACTURACION_EMITIR = 'facturacion.emitir';

    public const FACTURACION_NOTA = 'facturacion.nota';

    /**
     * @return list<string>
     */
    public static function todos(): array
    {
        return [
            self::CATALOGOS_VER, self::CATALOGOS_EDITAR,
            self::USUARIOS_VER, self::USUARIOS_EDITAR, self::ROLES_GESTIONAR,
            self::MESAS_VER, self::MESAS_EDITAR,
            self::ORDENES_VER_TODAS, self::ORDENES_ANULAR, self::ORDENES_DESCUENTO,
            self::POS_USAR, self::CAJA_REPORTE_X, self::CAJA_REPORTE_Z,
            self::CAJA_ARQUEO, self::CAJA_REEMBOLSO,
            self::COCINA_VER, self::COCINA_CORTAR_ITEM,
            self::INVENTARIO_VER, self::INVENTARIO_AJUSTAR, self::INVENTARIO_RECETAS,
            self::RESERVAS_VER, self::RESERVAS_EDITAR,
            self::VENTAS_VER, self::VENTAS_REPORTES,
            self::PARAMETROS_VER, self::PARAMETROS_EDITAR,
            self::AUDITORIA_VER,
            self::FACTURACION_VER, self::FACTURACION_EMITIR, self::FACTURACION_NOTA,
        ];
    }

    /**
     * @return array<string, array{description: string, permisos: list<string>}>
     */
    public static function grupos(): array
    {
        return [
            'Catálogos' => ['description' => 'Productos, insumos, alícuotas y métodos de pago', 'permisos' => [self::CATALOGOS_VER, self::CATALOGOS_EDITAR]],
            'Usuarios' => ['description' => 'Personal y roles', 'permisos' => [self::USUARIOS_VER, self::USUARIOS_EDITAR, self::ROLES_GESTIONAR]],
            'Salón' => ['description' => 'Mesas, zonas y salones', 'permisos' => [self::MESAS_VER, self::MESAS_EDITAR]],
            'Órdenes' => ['description' => 'Comandas, cuentas y anulaciones', 'permisos' => [self::ORDENES_VER_TODAS, self::ORDENES_ANULAR, self::ORDENES_DESCUENTO]],
            'Caja' => ['description' => 'Punto de venta, reportes y arqueos', 'permisos' => [self::POS_USAR, self::CAJA_REPORTE_X, self::CAJA_REPORTE_Z, self::CAJA_ARQUEO, self::CAJA_REEMBOLSO]],
            'Cocina' => ['description' => 'Pantalla de comandas', 'permisos' => [self::COCINA_VER, self::COCINA_CORTAR_ITEM]],
            'Inventario' => ['description' => 'Kardex, ajustes y recetas', 'permisos' => [self::INVENTARIO_VER, self::INVENTARIO_AJUSTAR, self::INVENTARIO_RECETAS]],
            'Reservas' => ['description' => 'Eventos y cursos', 'permisos' => [self::RESERVAS_VER, self::RESERVAS_EDITAR]],
            'Ventas' => ['description' => 'Reportes y estadísticas', 'permisos' => [self::VENTAS_VER, self::VENTAS_REPORTES]],
            'Facturación' => ['description' => 'Documentos fiscales, notas y cierres', 'permisos' => [self::FACTURACION_VER, self::FACTURACION_EMITIR, self::FACTURACION_NOTA]],
            'Parámetros' => ['description' => 'Tasas, alícuotas y políticas', 'permisos' => [self::PARAMETROS_VER, self::PARAMETROS_EDITAR]],
            'Auditoría' => ['description' => 'Bitácora de cambios', 'permisos' => [self::AUDITORIA_VER]],
        ];
    }
}
