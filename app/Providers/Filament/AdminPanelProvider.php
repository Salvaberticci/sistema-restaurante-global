<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\ActividadReciente;
use App\Filament\Widgets\BannerDemo;
use App\Filament\Widgets\PorMetodoPago;
use App\Filament\Widgets\ResumenDelDia;
use App\Filament\Widgets\TopProductos;
use App\Filament\Widgets\UtilidadPorDia;
use App\Filament\Widgets\VentasPorDia;
use App\Filament\Widgets\VentasPorHora;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName(config('app.name'))
            ->colors([
                'primary' => Color::Amber,
            ])
            // Modo oscuro por defecto: en una reunión con la pantalla proyectada
            // el ámbar sobre carbón rinde mucho más que el ámbar sobre blanco.
            ->defaultThemeMode(ThemeMode::Dark)
            ->font('Inter')
            // Capa de tema propia. Filament 3 compila el suyo con Tailwind 3 y
            // este proyecto usa Tailwind 4, así que en vez de compilar un tema
            // se registra un bundle que entra después en la cascada.
            ->viteTheme([
                'resources/css/admin/theme.css',
                'resources/js/admin.js',
            ], 'build')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                BannerDemo::class,
                ResumenDelDia::class,
                VentasPorDia::class,
                VentasPorHora::class,
                PorMetodoPago::class,
                TopProductos::class,
                UtilidadPorDia::class,
                ActividadReciente::class,
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
