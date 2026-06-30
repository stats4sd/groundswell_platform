<?php

namespace App\Filament\App\Pages;

use Filament\Support\Enums\Width;
use App\Models\Team;
use Filament\Pages\Page;
use App\Services\HelperService;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;

class SurveyDashboard extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-home';

    protected string $view = 'filament.app.pages.survey-dashboard';

    protected static ?string $navigationLabel = 'Survey Dashboard';

    protected static ?string $title = 'Survey Dashboard'; // set to empty because the dashboard has a custom header

    public static function canAccess(): bool
    {
        return auth()->user()->can('view survey dashboard');
    }

    public Team $team;

    public static function getNavigationLabel(): string
    {
        return t('Survey Dashboard');
    }

    public function getTitle(): string
    {
        return t('Survey Dashboard');
    }

    public function getHeader(): ?View
    {
        return view('components.survey-dashboard-header');
    }

    public function mount(): void
    {
        $this->team = HelperService::getCurrentOwner();
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

}
