<?php

namespace App\Livewire\SurveyLanguages;

use App\Models\Team;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Contracts\View\Factory;
use Illuminate\Foundation\Application;
use Illuminate\View\View;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Stats4sd\FilamentOdkLink\Exports\XlsformTemplateTranslationsExport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;

class TeamTranslationReview extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public Team $team;

    public Locale $locale;

    public function render(): Factory|Application|\Illuminate\Contracts\View\View|View|null
    {
        return view('livewire.survey-languages.team-translation-review');
    }

    public function downloadHouseholdAction(): Action
    {
        return Action::make('downloadHousehold')
            ->label('Download Household Survey Translation File')
            ->action(function () {

                $xlsformTemplate = $this->team->xlsforms->first()->xlsformTemplate; // Hardcoded assumption for now. TODO: Update!

                return Excel::download(new XlsformTemplateTranslationsExport($xlsformTemplate, $this->locale), 'HOLPA_household_translations - '.$this->locale->language_label.'.xlsx');

            });
    }

    public function downloadFieldworkAction(): Action
    {
        return Action::make('downloadFieldwork')
            ->label('Download Fieldwork Survey Translation File')
            ->action(function () {

                $xlsformTemplate = $this->team->xlsforms->last()->xlsformTemplate; // Hardcoded assumption for now. TODO: Update!

                return Excel::download(new XlsformTemplateTranslationsExport($xlsformTemplate, $this->locale), 'HOLPA_household_translations - '.$this->locale->language_label.'.xlsx');

            });
    }
}
