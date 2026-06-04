<?php

namespace App\Filament\App\Pages\Lisp;

use App\Filament\App\Pages\SurveyDashboard;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;

class OptionalModules extends Page implements HasTable
{
    use InteractsWithTable;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Localisation: Optional Modules';

    protected static string $view = 'filament.app.pages.lisp.optional-modules';

    protected ?string $summary = 'Please select the optional modules to be included in your survey.';

    public function getBreadcrumbs(): array
    {
        return [
            SurveyDashboard::getUrl() => 'Survey Dashboard',
            static::getUrl() => static::getTitle(),
        ];
    }

    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        return view('components.small-header', [
            'heading' => $this->getHeading(),
            'subheading' => $this->getSubheading(),
            'actions' => $this->getHeaderActions(),
            'breadcrumbs' => $this->getBreadcrumbs(),
            'summary' => $this->summary,
        ]);
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                XlsformModuleVersion::query()
                    ->whereNull('xlsform_module_id')
                    ->whereNull('owner_id')
            )
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_default')
                    ->boolean()
                    ->label('Default Version'),
                TextColumn::make('survey_rows_count')
                    ->counts('surveyRows')
                    ->label('# Questions'),
            ])
            ->paginated(false);
    }
}
