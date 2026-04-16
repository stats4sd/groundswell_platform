<?php

namespace App\Providers\Filament;

use Exception;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Stats4sd\FilamentOdkLink\OdkLinkAdmin;
use Stats4sd\FilamentTeamManagement\Filament\Admin\Resources\UserResource;
use Stats4sd\FilamentTeamManagement\Http\Middleware\CheckIfAdmin;

class AdminPanelProvider extends PanelProvider
{
    /**
     * @throws Exception
     */
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Red,
            ])
            // to include "Datasets" resource
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->resources([
                // Bring in Users resource from package stats4sd/filament-team-management
                UserResource::class,
            ])
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\\Filament\\Admin\\Widgets')
            ->pages([
            ])
            ->widgets([])
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn () => view('filament-team-management::admin-panel-title'),
            )
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
                CheckIfAdmin::class,
            ])->navigationItems([
                NavigationItem::make()
                    ->label(__('Return to Front end'))
                    ->icon('heroicon-o-home')
                    ->url(url('/app'))
                    ->sort(1),
            ])->darkMode(false)
            ->plugins([
                OdkLinkAdmin::make(),
            ]);
    }
}
