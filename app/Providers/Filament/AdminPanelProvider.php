<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\LatestOrders;
use App\Filament\Widgets\SalesChart;
use App\Filament\Widgets\SalesOverview;
use App\Http\Middleware\AuthenticateAdmin;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
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
            ->brandName('Cartopia')
            ->brandLogo(fn () => view('filament.admin.brand'))
            ->brandLogoHeight('2rem')
            ->favicon(asset('favicon.svg'))
            ->colors([
                // The store's greens: 600 is the green (#1F5A3D) behind
                // primary buttons, 500 the dark-mode green, 300 the dark-mode
                // chip text and 50 the chip; the rest are steps between them.
                // Color::hex() would rebuild a brighter mint palette around it.
                'primary' => [
                    50 => '#e6efe4',
                    100 => '#cfe6d6',
                    200 => '#b0dcc2',
                    300 => '#8fd3ae',
                    400 => '#5fae84',
                    500 => '#2e7a54',
                    600 => '#1f5a3d',
                    700 => '#1f4d38',
                    800 => '#1a4030',
                    900 => '#163526',
                    950 => '#0e2219',
                ],
                // Marigold (#F5B42E) at 400, which dark mode uses for warning
                // text, and the store's star (#E09A12) at 500. 600 and up are
                // a dark amber so light-mode warning text and badges on white
                // or on 50 (the store's selected cream) reach AA.
                'warning' => [
                    50 => '#fff6de',
                    100 => '#fce7b0',
                    200 => '#fad685',
                    300 => '#f8c65a',
                    400 => '#f5b42e',
                    500 => '#e09a12',
                    600 => '#8a5a00',
                    700 => '#6f4800',
                    800 => '#573900',
                    900 => '#452d00',
                    950 => '#2a1b00',
                ],
                // The store's green-tinted neutrals (resources/css/app.css):
                // 50 is the light page, 200/300 the light lines, 700 the dark
                // line, 800 the dark field, 900 the dark card and 950 the dark
                // page. 400–600 sit between them, dark enough for AA text.
                'gray' => [
                    50 => '#edf2e4',
                    100 => '#e6efe4',
                    200 => '#d3dcc8',
                    300 => '#c5d0b9',
                    400 => '#8d9c90',
                    500 => '#5f6f63',
                    600 => '#4d5e52',
                    700 => '#344a3d',
                    800 => '#223029',
                    900 => '#1a251f',
                    950 => '#111915',
                ],
            ])
            ->font('Figtree')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                SalesOverview::class,
                SalesChart::class,
                LatestOrders::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                AuthenticateAdmin::class,
            ]);
    }
}
