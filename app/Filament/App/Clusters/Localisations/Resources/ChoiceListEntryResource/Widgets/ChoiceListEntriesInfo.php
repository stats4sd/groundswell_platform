<?php

namespace App\Filament\App\Clusters\Localisations\Resources\ChoiceListEntryResource\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;

class ChoiceListEntriesInfo extends Widget
{
    protected static string $view = 'filament.app.clusters.lookup-tables.resources.choice-list-entry-resource.widgets.choice-list-entries-info';

    protected int|string|array $columnSpan = 'full';

    public string $choiceListName = '';

    /** @var Collection<SurveyRow> $surveyRows */
    public Collection $surveyRows;

    /** @var ChoiceList $choiceList */
    public ChoiceList $choiceList;

    public function mount(): void
    {   
        // only find choice list by list name if list name is not empty
        if ($this->choiceListName) {
            $this->choiceList = ChoiceList::query()->where('list_name', $this->choiceListName)->first();
        } else {
            // when user click "ADD NEW" button, error occurred because $this->choiceList must not be accessed before initialization

            // TODO: initialise choice list
            // Question: how to initialise choice list when choice list name is not available?
            
        }

        $this->refreshSurveyRows();
    }

    #[On('updated:choiceListName')]
    protected function refreshSurveyRows(): void
    {

        /** @var Collection $surveyRows */
        $this->surveyRows = SurveyRow::query()
            ->whereLike('type', 'select_multiple '.$this->choiceListName)
            ->orWhereLike('type', 'select_one '.$this->choiceListName)
            ->get()
            ->map(fn (SurveyRow $surveyRow): array => [
                'name' => $surveyRow->name,
                'label' => $surveyRow->languageStrings()
                    ->whereHas('locale', fn (Builder $query) => $query->where('language_id', 41))
                    ->where('language_string_type_id', 1)
                    ->first()->text ?? 'tbc',
            ]);
    }
}
