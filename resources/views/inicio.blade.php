<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <meta name="description" content="Sistema de gestión para restaurantes: punto de venta, cocina, reservas, inventario y facturación fiscal.">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="srg-landing min-h-screen bg-neutral-950 text-neutral-100 antialiased">

    {{-- Fondo: dos focos de luz ámbar, como la iluminación de un comedor --}}
    <div class="pointer-events-none fixed inset-0 -z-10">
        <div class="absolute -top-40 left-1/4 h-[32rem] w-[32rem] rounded-full bg-amber-500/10 blur-3xl"></div>
        <div class="absolute right-0 top-1/3 h-[26rem] w-[26rem] rounded-full bg-violet-500/10 blur-3xl"></div>
    </div>

    <header class="mx-auto flex max-w-6xl items-center justify-between px-6 py-6">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-amber-400 to-amber-600 text-neutral-950">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.8h6M4.5 19h15M7 19V9a5 5 0 0 1 10 0v10"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-semibold tracking-tight">{{ config('app.name') }}</p>
                <p class="text-xs text-neutral-500">Gestión integral de restaurantes</p>
            </div>
        </div>

        <a href="{{ url('/admin/login') }}"
           class="rounded-lg bg-white/5 px-4 py-2 text-sm font-medium ring-1 ring-white/10 transition hover:bg-white/10 hover:ring-white/20">
            Entrar al sistema
        </a>
    </header>

    <main class="mx-auto max-w-6xl px-6 pb-24">

        {{-- Portada ------------------------------------------------------ --}}
        <section class="srg-entrada pt-16 text-center sm:pt-24">
            <span class="inline-flex items-center gap-2 rounded-full bg-amber-500/10 px-3 py-1 text-xs font-medium text-amber-300 ring-1 ring-amber-500/20">
                Ambiente de demostración
            </span>

            <h1 class="mx-auto mt-6 max-w-3xl text-4xl font-semibold tracking-tight sm:text-6xl">
                El restaurante entero,
                <span class="bg-gradient-to-r from-amber-300 to-amber-600 bg-clip-text text-transparent">
                    en una sola pantalla
                </span>
            </h1>

            <p class="mx-auto mt-6 max-w-2xl text-lg text-neutral-400">
                Del mesero que toma la comanda en su tablet al Reporte Z que cierra el día.
                Punto de venta, cocina, reservas, inventario y facturación fiscal para Venezuela.
            </p>

            <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
                <a href="{{ url('/admin/login') }}"
                   class="rounded-xl bg-gradient-to-br from-amber-400 to-amber-600 px-6 py-3 text-sm font-semibold text-neutral-950 shadow-lg shadow-amber-500/20 transition hover:brightness-110">
                    Ver el panel
                </a>
                <a href="#modulos"
                   class="rounded-xl bg-white/5 px-6 py-3 text-sm font-medium ring-1 ring-white/10 transition hover:bg-white/10">
                    Conocer los módulos
                </a>
            </div>
        </section>

        {{-- Módulos ------------------------------------------------------ --}}
        <section id="modulos" class="mt-28">
            <h2 class="text-center text-3xl font-semibold tracking-tight">Módulos</h2>
            <p class="mx-auto mt-3 max-w-xl text-center text-neutral-400">
                Trece áreas que se usan todos los días. El backoffice en Filament,
                la operación en Livewire.
            </p>

            <div class="srg-escalonado mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    ['Operación', 'Mesas y salón, comandas desde la tablet y pantalla de cocina en vivo.', 'heroicon-o-signal', true],
                    ['Punto de venta', 'Cobro con pago mixto en bolívares y divisas, con IVA e IGTF calculados al momento.', 'heroicon-o-credit-card', true],
                    ['Pantalla de cocina', 'Los tickets llegan solos, con semáforo de tiempo y alerta sonora.', 'heroicon-o-fire', false],
                    ['Menú digital', 'El mismo menú que ve el cliente y que cobra el cajero.', 'heroicon-o-book-open', true],
                    ['Facturación', 'Documentos fiscales, notas de crédito y reportes X y Z.', 'heroicon-o-document-text', false],
                    ['Tasa de cambio', 'Bolívares en vivo y conversión congelada en cada documento.', 'heroicon-o-arrows-right-left', false],
                    ['Reservaciones', 'Eventos y cursos, pagados 100% por adelantado.', 'heroicon-o-calendar-days', false],
                    ['Inventario', 'Recetas por plato, kardex y alertas de stock.', 'heroicon-o-cube', true],
                    ['Estadísticas', 'Ventas por día, por hora, por método de pago y utilidad real.', 'heroicon-o-chart-bar', false],
                    ['Roles', 'Cinco roles con alcances distintos y todo cambio auditado.', 'heroicon-o-shield-check', true],
                    ['Catálogos', 'Productos, insumos, alícuotas y métodos de pago.', 'heroicon-o-rectangle-stack', true],
                    ['Auditoría', 'Quién cambió qué, cuándo y desde dónde.', 'heroicon-o-clipboard-document-list', false],
                    ['Parámetros', 'Tasas y políticas editables sin tocar código.', 'heroicon-o-adjustments-horizontal', true],
                ] as $i => [$titulo, $texto, $icono, $listo])
                    <div class="srg-card srg-card-hover p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-white/5 ring-1 ring-white/10">
                                @svg($icono, 'h-5 w-5 text-amber-400')
                            </div>

                            @if ($listo)
                                <span class="rounded-full bg-emerald-500/10 px-2 py-0.5 text-[0.65rem] font-medium text-emerald-300 ring-1 ring-emerald-500/20">
                                    Listo
                                </span>
                            @else
                                <span class="rounded-full bg-white/5 px-2 py-0.5 text-[0.65rem] font-medium text-neutral-400 ring-1 ring-white/10">
                                    En obra
                                </span>
                            @endif
                        </div>

                        <h3 class="mt-4 font-semibold">{{ $titulo }}</h3>
                        <p class="mt-1 text-sm leading-relaxed text-neutral-400">{{ $texto }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Acceso ------------------------------------------------------- --}}
        <section class="mt-28">
            <div class="srg-card mx-auto max-w-3xl p-8 text-center">
                <h2 class="text-2xl font-semibold tracking-tight">Entrar con una cuenta de prueba</h2>
                <p class="mt-2 text-sm text-neutral-400">
                    Cada rol ve una pantalla distinta. Es la forma más rápida de entender
                    cómo se reparten las responsabilidades.
                </p>

                <div class="mt-8 grid gap-3 text-left sm:grid-cols-2">
                    @foreach ([
                        'Administrador' => ['admin@restaurante.test', 'Todo el sistema'],
                        'Gerente' => ['gerente@restaurante.test', 'Reportes e inventario'],
                        'Cajero' => ['cajero@restaurante.test', 'Punto de venta y caja'],
                        'Mesero' => ['mesero@restaurante.test', 'Comandas desde su tablet'],
                    ] as $rol => [$correo, $descripcion])
                        <div class="srg-card flex items-center justify-between gap-3 p-4">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold">{{ $rol }}</p>
                                <p class="truncate text-xs text-neutral-500">{{ $correo }}</p>
                                <p class="mt-1 text-xs text-neutral-400">{{ $descripcion }}</p>
                            </div>
                            <span class="shrink-0 rounded bg-white/5 px-2 py-1 text-[0.7rem] font-mono text-neutral-400">demo1234</span>
                        </div>
                    @endforeach
                </div>

                <a href="{{ url('/admin/login') }}"
                   class="mt-8 inline-flex rounded-xl bg-gradient-to-br from-amber-400 to-amber-600 px-6 py-3 text-sm font-semibold text-neutral-950 transition hover:brightness-110">
                    Entrar al panel
                </a>
            </div>
        </section>
    </main>

    <footer class="border-t border-white/5 py-8">
        <p class="mx-auto max-w-6xl px-6 text-xs text-neutral-600">
            Ambiente de demostración. Los datos pertenecen a un restaurante ficticio y
            los valores fiscales están pendientes de confirmar con un contador.
        </p>
    </footer>

</body>
</html>