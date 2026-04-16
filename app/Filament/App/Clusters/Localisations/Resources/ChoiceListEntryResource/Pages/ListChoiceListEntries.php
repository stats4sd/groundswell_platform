<?php

namespace App\Filament\App\Clusters\Localisations\Resources\ChoiceListEntryResource\Pages;

use App\Filament\App\Clusters\Localisations\Resources\ChoiceListEntryResource;
use App\Filament\App\Pages\PlaceAdaptations\PlaceAdaptationsIndex;
use App\Filament\App\Pages\SurveyDashboard;
use App\Services\HelperService;
use Filament\Forms\Form;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;

class ListChoiceListEntries extends ListRecords
{
    protected static string $resource = ChoiceListEntryResource::class;

    protected ?string $heading = 'Contextualise choice lists';

    #[Url]
    public string $choiceListName = '';

    public ChoiceList $choiceList;

    public function mount(): void
    {
        parent::mount();

        if (!$this->choiceListName) {
            $this->choiceList = ChoiceList::where('is_localisable', true)
                ->where('has_custom_handling', false)
                ->first();

            $this->choiceListName = $this->choiceList->list_name;
        } else {
            $this->choiceList = ChoiceList::where('list_name', $this->choiceListName)->first();
        }
    }

    public function getBreadcrumbs(): array
    {
        return [
            SurveyDashboard::getUrl() => 'Survey dashboard',
            PlaceAdaptationsIndex::getUrl() => 'Place-based adaptations',
            static::getUrl() => static::getHeading(),
        ];
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->modifyQueryUsing(fn(Builder $query) => $query
                ->whereHas('choiceList', fn(Builder $query) => $query
                    ->where('choice_lists.list_name', $this->choiceListName)
                )
            )
            ->actions([
                EditAction::make()->visible(fn(ChoiceListEntry $record) => !$record->is_global_entry)
                    ->form(fn(Form $form) => $form->schema(fn() => $this->getResource()::getFormSchema($this->choiceList))),
                DeleteAction::make()->visible(fn(ChoiceListEntry $record) => !$record->is_global_entry),
                \Filament\Tables\Actions\Action::make('Toggle Removed')
                    ->label(fn(ChoiceListEntry $record) => $record->isRemoved(HelperService::getCurrentOwner()) ? 'Restore to Context' : 'Remove from Context')
                    ->visible(fn(ChoiceListEntry $record) => $record->is_global_entry)
                    ->action(fn(ChoiceListEntry $record) => $record->toggleRemoved(HelperService::getCurrentOwner())),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add new ' . Str::singular($this->choiceListName))
                    ->form(fn(Form $form) => $form
                        ->schema(fn() => $this->getResource()::getFormSchema($this->choiceList))),
                ]);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ChoiceListEntryResource\Widgets\ChoiceListEntriesInfo::make(['choiceListName' => $this->choiceListName]),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [


            //            Action::make('Mark as Complete')
            //                ->action(fn() => HelperService::getCurrentOwner()?->markLookupListAsComplete($this->choiceList))
            //                ->visible(fn() => !HelperService::getCurrentOwner()?->hasCompletedLookupList($this->choiceList))
            //                ->after(fn() => $this->redirect($this->getResource()::getUrl('index', ['choiceListName' => $this->choiceListName]))),
            //
            //            Action::make('Mark as Incomplete')
            //                ->action(fn() => HelperService::getCurrentOwner()?->markLookupListAsIncomplete($this->choiceList))
            //                ->visible(fn() => HelperService::getCurrentOwner()?->hasCompletedLookupList($this->choiceList))
            //                ->after(fn() => $this->redirect($this->getResource()::getUrl('index', ['choiceListName' => $this->choiceListName]))),

        ];
    }
}
