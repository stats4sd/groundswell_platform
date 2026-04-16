<?php

namespace App\Filament\App\Pages\Lisp;

use App\Filament\App\Pages\SurveyDashboard;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;

class LispWorkshop extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.app.pages.lisp.lisp-workshop';

    protected static ?string $title = 'Localisation: LISP';
    // protected ?string $subheading = 'Workshop';

    protected static bool $shouldRegisterNavigation = false;

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
