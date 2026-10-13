{{--
    Estadísticas: todas las gráficas del sistema.

    Usa el mismo componente `<x-filament-widgets::widgets>` que el panel de inicio
    y que la pantalla de Ventas. Con una sola pieza de maquetación, las tres
    pantallas comparten retícula, separación y comportamiento.
--}}
<x-filament-panels::page>
    <x-filament-widgets::widgets
        :columns="$this->getColumns()"
        :widgets="$this->getVisibleWidgets()"
    />
</x-filament-panels::page>