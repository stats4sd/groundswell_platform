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

        $this->choiceList = ChoiceList::query()->where('list_name', $this->choiceListName)->first();

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
