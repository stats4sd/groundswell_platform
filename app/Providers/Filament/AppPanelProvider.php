<?php

namespace App\Providers\Filament;

use App\Filament\App\Pages\Auth\EditProfile;
use App\Filament\App\Pages\Auth\Login;
use App\Filament\App\Pages\SurveyDashboard;
use App\Filament\App\Resources\TeamResource\Pages\ViewTeam;
use App\Models\Team;
use BetterFuturesStudio\FilamentLocalLogins\LocalLogins;
use Exception;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Stats4sd\FilamentTeamManagement\Filament\App\Pages\RegisterTeam;
use Stats4sd\FilamentTeamManagement\Http\Middleware\SetLatestTeamMiddleware;

class AppPanelProvider extends PanelProvider
{
    /**
     * @throws Exception
     */
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('app')
            ->tenant(Team::class)
            // any user can create a new team
            ->tenantRegistration(RegisterTeam::class)
            ->tenantMiddleware([
                SetLatestTeamMiddleware::class,
            ])
            ->profile(EditProfile::class, isSimple: false)
            ->login(Login::class)
            ->passwordReset()
            ->colors([
                'primary' => [
                    500 => "#c3432f",
                    600 => "#EB5A45",
                ],
                'blue' => [
                    50 => '63, 169, 245',
                    100 => '63, 169, 245',
                    200 => '63, 169, 245',
                    300 => '63, 169, 245',
                    400 => '63, 169, 245',
                    500 => '63, 169, 245',
                    600 => '63, 169, 245',
                    700 => '63, 169, 245',
                    800 => '63, 169, 245',
                    900 => '63, 169, 245',
                    950 => '63, 169, 245',
                ],
                'green' => [
                    50 => '216, 234, 208',
                    100 => '95, 163, 90',
                    200 => '95, 163, 90',
                    300 => '95, 163, 90',
                    400 => '95, 163, 90',
                    500 => '95, 163, 90',
                    600 => '95, 163, 90',
                    700 => '95, 163, 90',
                    800 => '95, 163, 90',
                    900 => '95, 163, 90',
                    950 => '95, 163, 90',
                ],
                'success' => [
                    50 => '95, 163, 90',
                    100 => '95, 163, 90',
                    200 => '95, 163, 90',
                    300 => '95, 163, 90',
                    400 => '95, 163, 90',
                    500 => '95, 163, 90',
                    600 => '95, 163, 90',
                    700 => '95, 163, 90',
                    800 => '95, 163, 90',
                    900 => '95, 163, 90',
                    950 => '95, 163, 90',
                ],
                'orange' => [
                    50 => '235, 90, 69',
                    100 => '235, 90, 69',
                    200 => '235, 90, 69',
                    300 => '235, 90, 69',
                    400 => '235, 90, 69',
                    500 => '235, 90, 69',
                    600 => '235, 90, 69',
                    700 => '235, 90, 69',
                    800 => '235, 90, 69',
                    900 => '235, 90, 69',
                    950 => '235, 90, 69',
                ],
                'danger' => [
                    50 => '235, 90, 69',
                    100 => '235, 90, 69',
                    200 => '235, 90, 69',
                    300 => '235, 90, 69',
                    400 => '235, 90, 69',
                    500 => '235, 90, 69',
                    600 => '235, 90, 69',
                    700 => '235, 90, 69',
                    800 => '235, 90, 69',
                    900 => '235, 90, 69',
                    950 => '235, 90, 69',
                ],

                'lightgreen' => [
                    '216, 234, 208',
                ],
            ])
            ->viteTheme('resources/css/filament/app/theme.css')
            // to include XlsformResource from main repo
            ->discoverResources(in: app_path('Filament/App/Resources'), for: 'App\\Filament\\App\\Resources')
            // to include "My Team" filament resource from package stats4sd/filament-team-management

            // to include ODK Form Management filament page
            ->discoverPages(in: app_path('Filament/App/Pages'), for: 'App\\Filament\\App\\Pages')
            // to include role register, program register, register filament pages from package stats4sd/filament-team-management
            ->discoverPages(in: app_path('../vendor/stats4sd/filament-team-management/src/Filament/App/Pages'), for: 'Stats4sd\\FilamentTeamManagement\\Filament\\App\\Pages')
            ->discoverClusters(in: app_path('Filament/App/Clusters'), for: 'App\\Filament\\App\\Clusters')
            ->pages([
                SurveyDashboard::class,
                EditProfile::class,
            ])
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn () => view('filament.app.pages.info-panels.team-without-xlsform'),
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
            ])
            ->navigationItems([
                NavigationItem::make()
                    ->label(__('Survey Dashboard'))
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->url(url('survey-dashboard')),
                NavigationItem::make()
                    ->label(__('Admin Panel'))
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->url(url('admin'))
                    ->visible(fn () => auth()->user()->can('access admin panel')),
                NavigationItem::make()
                    ->label(__('Program Admin Panel'))
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->url(url('program'))
                    ->visible(fn () => auth()->user()->can('access program admin panel')),
                NavigationItem::make()
                ->label(__('My Team'))
                ->icon('heroicon-o-home')
                ->url(fn() => ViewTeam::getUrl(['record' => Filament::getTenant()])),
                NavigationItem::make()
                ->label(__('Download User Guide'))
                ->icon('heroicon-o-arrow-down-tray')
                ->url(url('HOLPA Online tool guidance 2025.pdf')),
            ])
            ->darkMode(false)
            ->topNavigation()
            ->renderHook(PanelsRenderHook::SCRIPTS_BEFORE, fn () => view('filament.app.scripts'))
            ->plugins([
                new LocalLogins,
            ]);
    }
}
