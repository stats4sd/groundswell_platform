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
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Stats4sd\FilamentTeamManagement\Filament\App\Pages\RegisterProgram;
use Stats4sd\FilamentTeamManagement\Filament\Program\Pages\Dashboard;
use Stats4sd\FilamentTeamManagement\Http\Middleware\CheckIfProgramAdmin;
use Stats4sd\FilamentTeamManagement\Http\Middleware\SetLatestProgramMiddleware;
use Stats4sd\FilamentTeamManagement\Models\Program;

class ProgramPanelProvider extends PanelProvider
{
    /**
     * @throws Exception
     */
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('program')
            ->path('program')
            ->tenant(Program::class)
            // disable "Register New Program" option in multi-tenancy
            // new program should be created by admin, user should not be able to create a new program
            ->tenantRegistration(RegisterProgram::class)
            ->tenantMiddleware([
                SetLatestProgramMiddleware::class,
            ])
            ->login()
            ->colors([
                'primary' => Color::Blue,
            ])
            // to include "My Program" filament resource from package stats4sd/filament-team-management
            ->discoverResources(in: app_path('../vendor/stats4sd/filament-team-management/src/Filament/Program/Resources'), for: 'Stats4sd\\FilamentTeamManagement\\Filament\\Program\\Resources')
            ->discoverPages(in: app_path('Filament/Program/Pages'), for: 'App\\Filament\\Program\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Program/Widgets'), for: 'App\\Filament\\Program\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn () => view('filament-team-management::program-admin-panel-title'),
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
                CheckIfProgramAdmin::class,
            ])
            ->navigationItems([
                NavigationItem::make()
                    ->label(__('Return to Front end'))
                    ->icon('heroicon-o-home')
                    ->url(url('/app')),
            ])
            ->darkMode(false)
            ->plugins([]);
    }
}
