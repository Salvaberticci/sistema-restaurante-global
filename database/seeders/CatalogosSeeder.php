<?php

namespace Database\Seeders;

use App\Domain\Catalogos\Models\Categoria;
use App\Domain\Catalogos\Models\EstacionCocina;
use App\Domain\Catalogos\Models\Insumo;
use App\Domain\Catalogos\Models\MetodoPago;
use App\Domain\Catalogos\Models\Producto;
use App\Domain\Mesas\Models\UnidadMedida;
use Illuminate\Database\Seeder;

/**
 * La carta de Casa Mango.
 *
 * Precios en dólares, que es como se fija el precio en un restaurante venezolano
 * que cobra en ambas monedas. Son valores de ejemplo: sirven para que el demo
 * se vea creíble, no son los precios de nadie.
 *
 * Cada producto lleva `alicuota_codigo`, nunca un número. El valor sale de la
 * tabla de vigencias.
 */
class CatalogosSeeder extends Seeder
{
    public function run(): void
    {
        $this->unidades();
        $this->estaciones();
        $this->categorias();
        $this->productos();
        $this->insumos();
        $this->metodosPago();

        $this->command->newLine();
        $this->command->info(sprintf(
            'Catálogos: %d productos · %d insumos · %d categorías',
            Producto::count(),
            Insumo::count(),
            Categoria::count(),
        ));
    }

    private function unidades(): void
    {
        $unidades = [
            ['codigo' => 'UND', 'nombre' => 'Unidad', 'plural' => 'Unidades', 'decimales' => 0, 'permite_fraccion' => false],
            ['codigo' => 'POR', 'nombre' => 'Porción', 'plural' => 'Porciones', 'decimales' => 0, 'permite_fraccion' => false],
            ['codigo' => 'KG', 'nombre' => 'Kilogramo', 'plural' => 'Kilogramos', 'decimales' => 3, 'permite_fraccion' => true],
            ['codigo' => 'GR', 'nombre' => 'Gramo', 'plural' => 'Gramos', 'decimales' => 0, 'permite_fraccion' => true],
            ['codigo' => 'LT', 'nombre' => 'Litro', 'plural' => 'Litros', 'decimales' => 3, 'permite_fraccion' => true],
            ['codigo' => 'ML', 'nombre' => 'Mililitro', 'plural' => 'Mililitros', 'decimales' => 0, 'permite_fraccion' => true],
            ['codigo' => 'DOC', 'nombre' => 'Docena', 'plural' => 'Docenas', 'decimales' => 0, 'permite_fraccion' => false],
        ];

        foreach ($unidades as $unidad) {
            UnidadMedida::updateOrCreate(['codigo' => $unidad['codigo']], [...$unidad, 'activo' => true]);
        }
    }

    private function estaciones(): void
    {
        $estaciones = [
            [
                'codigo' => 'cocina_caliente', 'nombre' => 'Cocina Caliente', 'orden' => 1,
                'descripcion' => 'Platos de fogón y horno. Es la estación con más carga.',
                'icono' => 'heroicon-o-fire', 'tiempo_verde_min' => 15, 'tiempo_ambar_min' => 22,
            ],
            [
                'codigo' => 'barra', 'nombre' => 'Barra', 'orden' => 2,
                'descripcion' => 'Bebidas, coctelería y postres frios.',
                'icono' => 'heroicon-o-beaker', 'tiempo_verde_min' => 6, 'tiempo_ambar_min' => 10,
            ],
            [
                'codigo' => 'parrilla', 'nombre' => 'Parrilla', 'orden' => 3,
                'descripcion' => 'Carnes y asados a la parrilla.',
                'icono' => 'heroicon-o-squares-2x2', 'tiempo_verde_min' => 14, 'tiempo_ambar_min' => 20,
            ],
        ];

        foreach ($estaciones as $estacion) {
            EstacionCocina::updateOrCreate(['codigo' => $estacion['codigo']], [...$estacion, 'activo' => true]);
        }
    }

    private function categorias(): void
    {
        $categorias = [
            ['nombre' => 'Entradas', 'orden' => 1, 'icono' => 'heroicon-o-sparkles', 'descripcion' => 'Para empezar.'],
            ['nombre' => 'Platos principales', 'orden' => 2, 'icono' => 'heroicon-o-square-3-stack-3d', 'descripcion' => 'El corazón de la carta.'],
            ['nombre' => 'Bebidas', 'orden' => 3, 'icono' => 'heroicon-o-beaker', 'descripcion' => 'Sin y con alcohol.'],
            ['nombre' => 'Postres', 'orden' => 4, 'icono' => 'heroicon-o-cake', 'descripcion' => 'El cierre dulce.'],
            ['nombre' => 'Combos', 'orden' => 5, 'icono' => 'heroicon-o-gift', 'descripcion' => ' Plato, bebida y postre.'],
        ];

        foreach ($categorias as $categoria) {
            Categoria::updateOrCreate(
                ['nombre' => $categoria['nombre']],
                [...$categoria, 'activo' => true, 'mostrar_menu_digital' => true],
            );
        }
    }

    /**
     * @param  string  $estacion  Código de la estación, o '' si no pasa por cocina.
     */
    private function producto(
        string $codigo,
        string $nombre,
        string $categoria,
        string $precio,
        string $estacion,
        string $unidad = 'POR',
        string $alicuota = 'IVA_G',
        bool $bebida = false,
        string $descripcion = '',
    ): void {
        Producto::updateOrCreate(
            ['codigo' => $codigo],
            [
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'categoria_id' => Categoria::where('nombre', $categoria)->value('id'),
                'unidad_medida_id' => UnidadMedida::where('codigo', $unidad)->value('id'),
                'estacion_cocina_id' => $estacion === ''
                    ? null
                    : EstacionCocina::where('codigo', $estacion)->value('id'),
                'precio_venta' => $precio,
                'alicuota_codigo' => $alicuota,
                'requiere_preparacion' => $estacion !== '',
                'es_bebida' => $bebida,
                'es_insumo' => false,
                'disponible' => true,
                'mostrar_menu_digital' => true,
            ],
        );
    }

    private function productos(): void
    {
        // Entradas
        $this->producto('ENT-01', 'Tequeños (6 unidades)', 'Entradas', '3.50', 'cocina_caliente', descripcion: 'Queso blanco con ají dulce.');
        $this->producto('ENT-02', 'Patacón con queso', 'Entradas', '2.50', 'cocina_caliente');
        $this->producto('ENT-03', 'Croquetas de bacalao (4)', 'Entradas', '4.00', 'cocina_caliente');
        $this->producto('ENT-04', 'Empanadas de carne (3)', 'Entradas', '2.00', 'cocina_caliente');
        $this->producto('ENT-05', 'Ceviche de camarón', 'Entradas', '6.50', '', descripcion: 'Al natural, con cilantro y limón.');
        $this->producto('ENT-06', 'Ensalada de aguacate', 'Entradas', '3.75', '');

        // Platos principales
        $this->producto('PRI-01', 'Pabellón criollo', 'Platos principales', '7.50', 'cocina_caliente', descripcion: 'El de toda la vida: arroz, caraotas, carne mechada, jamón y morrocoyo.');
        $this->producto('PRI-02', 'Cachapas con pollo', 'Platos principales', '6.00', 'cocina_caliente');
        $this->producto('PRI-03', 'Arepas con queso (2)', 'Platos principales', '2.00', 'cocina_caliente');
        $this->producto('PRI-04', 'Pastel de casas (3)', 'Platos principales', '5.00', 'cocina_caliente');
        $this->producto('PRI-05', 'Arroz a la Julieta', 'Platos principales', '5.50', 'cocina_caliente', descripcion: 'Pollo guisado con crema.');
        $this->producto('PRI-06', 'Churrasco de res', 'Platos principales', '9.50', 'parrilla');
        $this->producto('PRI-07', 'Pasta al pesto', 'Platos principales', '6.00', 'cocina_caliente');
        $this->producto('PRI-08', 'Pizza margarita', 'Platos principales', '5.00', 'cocina_caliente');
        $this->producto('PRI-09', 'Cazuela de mariscos', 'Platos principales', '11.00', '', descripcion: 'Para compartir.');
        $this->producto('PRI-10', 'Salmón a la parrilla con ensalada', 'Platos principales', '10.50', 'parrilla');

        // Bebidas
        $this->producto('BEB-01', 'Malta', 'Bebidas', '1.00', 'barra', 'UND', bebida: true);
        $this->producto('BEB-02', 'Refresco', 'Bebidas', '1.20', 'barra', 'UND', bebida: true);
        $this->producto('BEB-03', 'Jugo de naranja natural', 'Bebidas', '1.80', 'barra', 'UND', bebida: true, descripcion: 'Exprimido del día.');
        $this->producto('BEB-04', 'Agua mineral', 'Bebidas', '0.80', '', 'UND', bebida: true);
        $this->producto('BEB-05', 'Cerveza', 'Bebidas', '2.50', 'barra', 'UND', bebida: true);
        $this->producto('BEB-06', 'Cerveza artesanal', 'Bebidas', '4.00', 'barra', 'UND', bebida: true);
        $this->producto('BEB-07', 'Mojito', 'Bebidas', '3.50', 'barra', 'UND', bebida: true);
        $this->producto('BEB-08', 'Café', 'Bebidas', '0.80', 'barra', 'UND', bebida: true);

        // Postres
        $this->producto('POS-01', 'Tres leches', 'Postres', '3.00', 'barra', descripcion: 'Con coco y canela.');
        $this->producto('POS-02', 'Flan con caramelo', 'Postres', '2.20', 'barra');
        $this->producto('POS-03', 'Torta de chocolate', 'Postres', '2.80', 'barra');
        $this->producto('POS-04', 'Helado de mango (2 bolas)', 'Postres', '2.50', 'barra');

        // Combos
        $this->producto('COM-01', 'Combo Casero', 'Combos', '12.00', 'cocina_caliente', descripcion: 'Pabellón, jugo natural y postre.');
        $this->producto('COM-02', 'Comboparrilla', 'Combos', '15.50', 'parrilla', descripcion: 'Churrasco, ensalada, malta y flan.');
    }

    private function insumos(): void
    {
        $insumos = [
            ['INS-01', 'Arroz blanco', 'KG', '1.20', '25', '5', 'Molinos del Centro'],
            ['INS-02', 'Caraotas negras', 'KG', '2.80', '12', '3', 'Distribuidora El Oriente'],
            ['INS-03', 'Carne mechada', 'KG', '7.50', '8', '2', 'Carnes del Centro'],
            ['INS-04', 'Jamón', 'KG', '6.20', '4', '1', 'Carnes del Centro'],
            ['INS-05', 'Plátano maduro', 'KG', '0.90', '20', '5', 'Mercado de Chacao'],
            ['INS-06', 'Queso blanco duro', 'KG', '8.50', '6', '2', 'Lácteos Los Andes'],
            ['INS-07', 'Harina de maíz precocida', 'KG', '1.40', '15', '4', 'Molinos del Centro'],
            ['INS-08', 'Aceite de girasol', 'LT', '2.10', '30', '8', 'Distribuidora El Centro'],
            ['INS-09', 'Azúcar', 'KG', '0.85', '40', '10', 'Molinos del Centro'],
            ['INS-10', 'Pollo entero', 'KG', '3.40', '18', '5', 'Carnes del Centro'],
            ['INS-11', 'Res (costillar)', 'KG', '11.00', '10', '3', 'Carnes del Centro'],
            ['INS-12', 'Salmón fresco', 'KG', '16.50', '5', '2', 'Pescadería del Puerto'],
            ['INS-13', 'Leche entera', 'LT', '0.75', '24', '6', 'Lácteos Los Andes'],
            ['INS-14', 'Coco rallado', 'KG', '4.20', '3', '1', 'Distribuidora El Centro'],
            ['INS-15', 'Camarón limpio', 'KG', '13.50', '6', '2', 'Pescadería del Puerto'],
        ];

        foreach ($insumos as [$codigo, $nombre, $unidad, $precio, $stock, $minimo, $proveedor]) {
            Insumo::updateOrCreate(
                ['codigo' => $codigo],
                [
                    'nombre' => $nombre,
                    'unidad_medida_id' => UnidadMedida::where('codigo', $unidad)->value('id'),
                    'precio_compra' => $precio,
                    'stock_inicial' => $stock,
                    'stock_minimo' => $minimo,
                    'merma_porcentaje' => '5.00',
                    'alicuota_codigo' => 'IVA_G',
                    'proveedor' => $proveedor,
                    'activo' => true,
                ],
            );
        }
    }

    private function metodosPago(): void
    {
        /**
         * Los métodos en divisa llevan `genera_igtf` y su perfil. Los que no
         * generan IGTF lo dicen explícitamente en vez de dejarlo en falso por
         * olvido: que "no" y "todavía no lo definimos" sean el mismo valor hace
         * que nadie note cuándo falta un método.
         */
        $metodos = [
            ['codigo' => 'efectivo_bs', 'nombre' => 'Efectivo en bolívares', 'orden' => 1, 'es_efectivo' => true, 'es_divisa' => false, 'genera_igtf' => false, 'requiere_referencia' => false, 'icono' => 'heroicon-o-banknotes'],
            ['codigo' => 'pago_movil', 'nombre' => 'Pago móvil', 'orden' => 2, 'es_efectivo' => false, 'es_divisa' => false, 'genera_igtf' => false, 'requiere_referencia' => true, 'icono' => 'heroicon-o-device-phone-mobile'],
            ['codigo' => 'tarjeta_debito', 'nombre' => 'Tarjeta de débito', 'orden' => 3, 'es_efectivo' => false, 'es_divisa' => false, 'genera_igtf' => false, 'requiere_referencia' => true, 'icono' => 'heroicon-o-credit-card'],
            ['codigo' => 'efectivo_usd', 'nombre' => 'Efectivo en dólares', 'orden' => 4, 'es_efectivo' => true, 'es_divisa' => true, 'genera_igtf' => true, 'igtf_perfil_codigo' => 'IGTF_EFECTIVO', 'requiere_referencia' => false, 'icono' => 'heroicon-o-currency-dollar'],
            ['codigo' => 'transferencia_usd', 'nombre' => 'Transferencia en divisas', 'orden' => 5, 'es_efectivo' => false, 'es_divisa' => true, 'genera_igtf' => true, 'igtf_perfil_codigo' => 'IGTF_TRANSFERENCIA', 'requiere_referencia' => true, 'icono' => 'heroicon-o-arrow-path'],
            ['codigo' => 'punto_venta_usd', 'nombre' => 'Punto de venta en divisa', 'orden' => 6, 'es_efectivo' => false, 'es_divisa' => true, 'genera_igtf' => true, 'igtf_perfil_codigo' => 'IGTF_PUNTO_VENTA', 'requiere_referencia' => true, 'icono' => 'heroicon-o-credit-card'],
            ['codigo' => 'zelle', 'nombre' => 'Zelle', 'orden' => 7, 'es_efectivo' => false, 'es_divisa' => true, 'genera_igtf' => false, 'igtf_perfil_codigo' => null, 'requiere_referencia' => true, 'icono' => 'heroicon-o-arrow-right-end-on-rectangle'],
        ];

        foreach ($metodos as $metodo) {
            MetodoPago::updateOrCreate(
                ['codigo' => $metodo['codigo']],
                [...$metodo, 'activo' => true],
            );
        }
    }
}
