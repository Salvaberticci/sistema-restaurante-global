{{--
    Pantalla de cocina.

    Diseñada para verse a dos metros, con las manos ocupadas y sin que nadie la
    toque. Botones grandes, una sola acción por plato y el color haciendo el
    trabajo: verde está en tiempo, ámaranza, rojo se pasó.

    El `wire:poll` es lo que la hace "en vivo". Con varias tablets, quien mueve
    un plato lo ve el resto en dos segundos sin recargar.
--}}
<div
    class="min-h-screen bg-neutral-950 text-neutral-100"
    wire:poll.{{ $periodo }}ms
>
    {{-- Cabecera: qué toca, cuánto tarda y dónde se está cocinado --}}
    <header class="sticky top-0 z-20 border-b border-neutral-800 bg-neutral-950/95 backdrop-blur">
        <div class="mx-auto flex max-w-[1800px] flex-wrap items-center gap-4 px-6 py-4">
            <div class="mr-auto">
                <h1 class="text-2xl font-bold tracking-tight">Cocina</h1>
                <p class="text-sm text-neutral-400">
                    {{ $tickets->count() }} {{ $tickets->count() === 1 ? 'comanda' : 'comandas' }}
                    en pantalla
                </p>
            </div>

            {{-- Semáforo. Los mismos tres estados que usa el parámetro, y los
                 números salen de la misma consulta que los ítems. --}}
            <div class="flex items-center gap-2">
                <span class="flex items-center gap-2 rounded-lg bg-emerald-500/15 px-4 py-2 text-emerald-300">
                    <span class="text-xl font-bold">{{ $semaforo['ok'] }}</span>
                    <span class="text-xs uppercase tracking-wide">en tiempo</span>
                </span>
                <span class="flex items-center gap-2 rounded-lg bg-amber-500/15 px-4 py-2 text-amber-300">
                    <span class="text-xl font-bold">{{ $semaforo['avisos'] }}</span>
                    <span class="text-xs uppercase tracking-wide">avisos</span>
                </span>
                <span class="flex items-center gap-2 rounded-lg bg-rose-500/15 px-4 py-2 text-rose-300">
                    <span class="text-xl font-bold">{{ $semaforo['tarde'] }}</span>
                    <span class="text-xs uppercase tracking-wide">tarde</span>
                </span>
            </div>

            <div class="flex items-center gap-2 text-xs text-neutral-400">
                @foreach ($estaciones as $estacion)
                    <span class="rounded-full border border-neutral-700 px-3 py-1">{{ $estacion }}</span>
                @endforeach
            </div>
        </div>

        {{-- Conteo por estado: dice de un vistazo si la cocina está ahogada --}}
        <div class="mx-auto flex max-w-[1800px] gap-6 px-6 pb-3 text-sm">
            <span>Pendientes <strong class="text-amber-300">{{ $resumen['pendientes'] }}</strong></span>
            <span>En preparación <strong class="text-sky-300">{{ $resumen['preparacion'] }}</strong></span>
            <span>Listos <strong class="text-emerald-300">{{ $resumen['listos'] }}</strong></span>
            <span>Servidos <strong class="text-neutral-400">{{ $resumen['servidos'] }}</strong></span>
        </div>
    </header>

    {{-- Cambio de vista. "Para pasar" es la pantalla que se mira cada diez
         segundos; la otra solo cuando hay un montón de listos. --}}
    <nav class="mx-auto flex max-w-[1800px] gap-2 px-6 pt-6">
        <button
            type="button"
            wire:click="cambiarVista('pendientes')"
            @class([
                'rounded-lg px-4 py-2 text-sm font-semibold transition',
                'bg-amber-500 text-neutral-950' => $vista === 'pendientes',
                'bg-neutral-800 text-neutral-300 hover:bg-neutral-700' => $vista !== 'pendientes',
            ])
        >
            Para preparar
        </button>
        <button
            type="button"
            wire:click="cambiarVista('listos')"
            @class([
                'rounded-lg px-4 py-2 text-sm font-semibold transition',
                'bg-emerald-500 text-neutral-950' => $vista === 'listos',
                'bg-neutral-800 text-neutral-300 hover:bg-neutral-700' => $vista !== 'listos',
            ])
        >
            Para pasar ({{ $resumen['listos'] }})
        </button>
    </nav>

    {{-- Las comandas --}}
    <main class="mx-auto grid max-w-[1800px] grid-cols-1 gap-4 px-6 py-6 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
        @forelse ($tickets as $ticket)
@php
                    $masViejo = $ticket->items->min('created_at');
                    $espera = $masViejo ? (int) $masViejo->diffInMinutes(now()) : 0;
                    $tono = $espera > $umbrales['ambar'] ? 'rose' : ($espera > $umbrales['verde'] ? 'amber' : 'emerald');
                @endphp

            <article
                @class([
                    'rounded-xl border-2 bg-neutral-900 p-5 transition',
                    'border-rose-500/70' => $tono === 'rose',
                    'border-amber-500/60' => $tono === 'amber',
                    'border-emerald-500/40' => $tono === 'emerald',
                ])
                wire:key="orden-{{ $ticket->id }}"
            >
                <header class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <p class="text-2xl font-bold tracking-tight">
                            {{ $ticket->mesa?->nombre ?? 'Para llevar' }}
                        </p>
                        <p class="text-sm text-neutral-400">
                            {{ $ticket->numero }}
                            @if ($ticket->mesero)
                                · {{ $ticket->mesero->name }}
                            @endif
                        </p>
                    </div>

                    {{-- El tiempo lleva el color: verde bien, rojo hay que correr --}}
                    <span
                        @class([
                            'rounded-lg px-3 py-2 text-lg font-bold tabular-nums',
                            'bg-emerald-500/20 text-emerald-300' => $tono === 'emerald',
                            'bg-amber-500/20 text-amber-300' => $tono === 'amber',
                            'bg-rose-500/20 text-rose-300' => $tono === 'rose',
                        ])
                    >
                        {{ $espera }}′
                    </span>
                </header>

                <ul class="space-y-2">
                    @foreach ($ticket->items as $item)
                        @php
                            $proximo = match ($item->estado) {
                                'pendiente' => 'en_preparacion',
                                'en_preparacion' => 'listo',
                                'listo' => 'servido',
                                default => null,
                            };
                            $texto = match ($proximo) {
                                'en_preparacion' => 'Empezar',
                                'listo' => 'Listo',
                                'servido' => 'Servido',
                                default => '—',
                            };
                        @endphp

                        <li class="flex items-center gap-3" wire:key="item-{{ $item->id }}">
                            <span class="w-10 shrink-0 text-center text-lg font-bold tabular-nums text-neutral-400">
                                {{ (int) $item->cantidad }}×
                            </span>

                            <div class="min-w-0 flex-1">
                                <p @class([
                                    'truncate text-lg font-semibold',
                                    'text-emerald-300' => $item->estado === 'listo',
                                ])>
                                    {{ $item->producto_nombre }}
                                </p>
                                @if ($item->notas)
                                    <p class="truncate text-sm text-amber-300">{{ $item->notas }}</p>
                                @endif
                            </div>

                            @if ($proximo)
                                <button
                                    type="button"
                                    wire:click="moverItem({{ $ticket->id }}, {{ $item->id }}, '{{ $proximo }}')"
                                    class="shrink-0 rounded-lg bg-neutral-800 px-4 py-3 text-sm font-bold uppercase tracking-wide text-neutral-200 transition hover:bg-amber-500 hover:text-neutral-950 active:scale-95"
                                >
                                    {{ $texto }}
                                </button>
                            @else
                                <span class="shrink-0 rounded-lg bg-neutral-800/50 px-4 py-3 text-sm font-bold uppercase tracking-wide text-neutral-500">
                                    Servido
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ul>

                @if ($ticket->items->isNotEmpty() && $ticket->items->every(fn ($i) => $i->estado !== 'pendiente' && $i->estado !== 'en_preparacion'))
                    <button
                        type="button"
                        wire:click="servirOrden({{ $ticket->id }})"
                        class="mt-4 w-full rounded-lg border border-emerald-500/40 py-2 text-sm font-bold uppercase tracking-wide text-emerald-300 transition hover:bg-emerald-500 hover:text-neutral-950"
                    >
                        Servir todo
                    </button>
                @endif
            </article>
        @empty
            <div class="col-span-full py-24 text-center">
                <p class="text-2xl font-semibold text-neutral-500">
                    {{ $vista === 'listos' ? 'Nada para pasar' : 'No hay comandas pendientes' }}
                </p>
                <p class="mt-2 text-neutral-600">
                    @if ($vista === 'listos')
                        Cuando un plato esté listo aparecerá aquí para que alguien lo lleve a la mesa.
                    @else
                        Las comandas del mesero aparecen solas en cuanto las envía.
                    @endif
                </p>
            </div>
        @endforelse
    </main>
</div>