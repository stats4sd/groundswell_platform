<?php

namespace App\Filament\App\Pages\Lisp;

use App\Filament\App\Pages\SurveyDashboard;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Livewire\Attributes\Url;

class LispIndicators extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.app.pages.lisp.lisp-indicators';

    protected static ?string $title = 'Localisation: LISP';
    // protected ?string $subheading = 'Customise indicators';

    protected static bool $shouldRegisterNavigation = false;

    #[Url('tab')]
    public ?string $activeTab = 'local';

    protected $listeners = ['switch-to-match-tab' => 'setActiveTab'];

    public function setActiveTab($tab): void
    {
        $this->activeTab = $tab;
    }

    public function getBreadcrumbs(): array
    {
        return [
            SurveyDashboard::getUrl() => 'Survey Dashboard',
            LispIndex::getUrl() => 'Localisation: LISP',
            static::getUrl() => static::getSubheading(),
        ];
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }
}
