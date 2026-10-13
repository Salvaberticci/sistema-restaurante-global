{{--
    Ventas: los widgets arriba y la tabla de cuentas abajo.

    Se copia la estructura de la vista de lista de Filament y se le meten los
    widgets entre la cabecera y la tabla. El componente `<x-filament-widgets::widgets>`
    es el mismo que usa el panel de inicio, con lo que la retícula y el orden de
    las gráficas quedan iguales en las dos pantallas.
--}}
<x-filament-panels::page
    class="fi-resource-list-records-page fi-resource-ventas"
>
    <div class="flex flex-col gap-y-6">
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE, scopes: $this->getRenderHookScopes()) }}

        <x-filament-widgets::widgets
            :columns="$this->getColumns()"
            :widgets="$this->getVisibleWidgets()"
        />

        {{ $this->table }}

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER, scopes: $this->getRenderHookScopes()) }}
    </div>
</x-filament-panels::page>