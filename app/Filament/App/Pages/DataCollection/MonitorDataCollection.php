<?php

namespace App\Filament\App\Pages\DataCollection;

use App\Filament\App\Pages\SurveyDashboard;
use App\Filament\Shared\WithCompletionStatusBar;
use App\Livewire\SubmissionsTableView;
use App\Models\SampleFrame\Location;
use App\Models\SampleFrame\LocationLevel;
use App\Models\Team;
use App\Services\HelperService;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;

class MonitorDataCollection extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;
    use WithCompletionStatusBar;

    public string $completionProp = 'data_collection_complete';
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.app.pages.data-collection.monitor-data-collection';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return auth()->user()->can('view monitor data collection');
    }

    public Team $team;
    public Collection $shinyData;

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    public function mount(): void
    {
        $this->team = HelperService::getCurrentOwner();

        // get the forms or nulls:
        $xlsforms = $this->team->xlsforms()->get();

        $regForm = $xlsforms->filter(fn(Xlsform $xlsform): bool => str_contains(strtolower($xlsform->title), 'reg'))->first();
        $indicatorForm = $xlsforms->filter(fn(Xlsform $xlsform): bool => str_contains(strtolower($xlsform->title), 'indicators'))->first();
        $womensForm = $xlsforms->filter(fn(Xlsform $xlsform): bool => str_contains(strtolower($xlsform->title), 'womens'))->first();



        $this->shinyData = collect([

        ### Temporarily don't send this information - use the shiny .env vars instead for demo


            'odk_project_id' => $this->team->odkProject->id,

            # 'reg_form_xml_id' => $regForm->odk_id ?? null,
            # 'reg_form_enketo_id' => $regForm->enketo_id ?? null,

            # 'indicators_form_xml_id' => $indicatorForm->odk_id ?? null,
            # 'indicators_form_enketo_id' => $regForm->enketo_id ?? null,

            # 'womens_form_xml_id' => $womensForm->odk_id ?? null,
            # 'womens_form_enketo_id' => $womensForm->enketo_id ?? null,

            'language' => app()->getLocale(),
        ]);


    }

    public function getBreadcrumbs(): array
    {
        return [
            SurveyDashboard::getUrl() => t('Survey Dashboard'),
            static::getUrl() => t('Monitor Data Collection'),
        ];
    }

}
