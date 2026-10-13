{{--
    Actividad reciente. No es una tabla: es una tira con el dato que un dueño
    mira de reojo, que es cuánto tardó cada mesa en pagar.
--}}
<div class="srg-escalonado grid gap-3 sm:grid-cols-2 xl:grid-cols-3">

    @forelse ($ordenes as $orden)
        <div class="srg-card srg-card-hover flex items-center gap-4 p-4">

            <div class="shrink-0">
                <div class="srg-semaforo" data-nivel="{{ $orden->estado === \App\Domain\Ordenes\Enums\EstadoOrden::Cobrada ? 'verde' : 'ambar' }}"></div>
            </div>

            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <span class="font-semibold">#{{ $orden->numero }}</span>
                    <span class="rounded-full bg-white/10 px-2 py-0.5 text-[0.68rem] font-medium text-white/70">
                        {{ $orden->canal->etiqueta() }}
                    </span>
                </div>

                <p class="mt-0.5 truncate text-xs text-white/55">
                    {{ $orden->mesa?->nombre ? 'Mesa '.$orden->mesa->nombre : $orden->canal->etiqueta() }}
                    @if ($orden->mesero)
                        · {{ $orden->mesero->name }}
                    @endif
                    @if ($orden->duracionMinutos())
                        · {{ $orden->duracionMinutos() }} min
                    @endif
                </p>
            </div>

            <div class="shrink-0 text-right">
                <p class="srg-numero text-base">${{ number_format((float) $orden->total, 2) }}</p>
                <p class="text-[0.68rem] text-white/45">
                    {{ $orden->items->count() }} {{ $orden->items->count() === 1 ? 'ítem' : 'ítems' }}
                </p>
            </div>
        </div>

    @empty
        <div class="col-span-full rounded-xl border border-dashed border-white/15 p-8 text-center">
            <p class="text-sm text-white/50">Todavía no hay cuentas cobradas.</p>
            <p class="mt-1 text-xs text-white/35">Cuando el cajero cobre la primera, aparece aquí.</p>
        </div>
    @endforelse
</div>