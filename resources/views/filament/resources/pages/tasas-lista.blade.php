{{--
    Tasa de cambio: la tasa vigente y la curva arriba, el historial abajo.

    Misma pieza de maquetación que la pantalla de Ventas. Tres pantallas con tres
    vues distintas harían que la retícula se fuera degrading sin que nadie lo note.
--}}
<x-filament-panels::page
    class="fi-resource-list-records-page fi-resource-tasas-cambio"
>
    <div class="flex flex-col gap-y-6">
        <x-filament-widgets::widgets
            :columns="$this->getColumns()"
            :widgets="$this->getVisibleWidgets()"
        />

        {{ $this->table }}
    </div>
</x-filament-panels::page>