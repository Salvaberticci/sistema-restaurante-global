{{-- Aviso de modo demostración. Solo se renderiza si config('demo.modo'). --}}
<div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-950/40">
    <div class="flex items-start gap-3">
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
        </svg>

        <div class="min-w-0 flex-1 text-sm">
            <p class="font-semibold text-amber-900 dark:text-amber-100">Modo demostración</p>

            <p class="mt-1 text-amber-800 dark:text-amber-200">
                Los datos pertenecen a un restaurante ficticio y los valores fiscales
                están marcados como pendientes de confirmar con un contador.
                No usar con datos reales.
            </p>

            @if (filled($sucursales))
                <p class="mt-2 text-xs text-amber-700 dark:text-amber-300">
                    Sucursales en el demo: {{ $sucursales->join(', ') }}
                </p>
            @endif
        </div>
    </div>
</div>