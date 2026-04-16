<?php

namespace App\Filament\App\Pages\PlaceAdaptations;

use App\Filament\App\Pages\SurveyDashboard;
use App\Filament\Shared\WithCompletionStatusBar;
use App\Models\Team;
use App\Services\HelperService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\On;
use Stats4sd\FilamentOdkLink\Events\XlsformDraftWasDeployed;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;

class InitialPilot extends Page implements HasTable, HasInfolists, HasActions
{
    use InteractsWithTable;
    use InteractsWithForms;
    use InteractsWithActions;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.app.pages.place-adaptations.initial-pilot';
    protected ?string $heading = "Survey Testing - Initial Pilot";
    // protected ?string $subheading = "Test with local researchers and practitioners to review the initial localisations";

    public Team $team;

    /** @var \Illuminate\Database\Eloquent\Collection<Xlsform> */
    public \Illuminate\Database\Eloquent\Collection $xlsforms;

    public function mount(): void
    {
        $this->team = HelperService::getCurrentOwner();
        $this->xlsforms = $this->team->xlsforms()->get();

        $this->team->deployDraftForms();
    }

    #[On('echo:xlsforms,.FilamentOdkLink.XlsformDraftWasDeployed')]
    public function handleXlsformDraftWasDeployed($event): void
    {
        $this->xlsforms->find($event['xlsformId'])->refresh();
    }

    public function getBreadcrumbs(): array
    {
        return [
            SurveyDashboard::getUrl() => 'Survey Dashboard',
            PlaceAdaptationsIndex::getUrl() => 'Place Adaptations',
            static::getUrl() => static::getTitle(),
        ];
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getRecord(): Team
    {
        return HelperService::getCurrentOwner();
    }

    public function viewSubmissionsAction(): Action
    {
        return Action::make('viewSubmissions')
            ->color('blue')
            ->extraAttributes(['class' => 'buttona'])
            ->url('#');
    }


    public function table(Table $table): Table
    {
        return $table
            ->heading('Draft Submissions')
            ->query(fn(): Builder => Submission::onlyDraftData())
            ->recordTitleAttribute('uuid')
            ->columns([
                TextColumn::make('xlsform_title')
                    ->grow(false),
                TextColumn::make('xlsformVersion.odk_version'),
                TextColumn::make('submitted_by'),
                TextColumn::make('submitted_at'),
            ])
            ->filters([
                //
            ])
            ->actions([
                \Filament\Tables\Actions\Action::make('view')
                    ->modalContent(fn(Submission $record) => view('filament.app.pages.submissions.modal_view', ['submission' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->headerActions([
                TableAction::make('test-on-odk-central')
                    ->url(fn() => HelperService::getCurrentOwner()->odkProject->odk_url),
                TableAction::make('pull-submissions')
                    ->label('Manually Get Submissions')
                    ->action(function (self $livewire) {

                        $count = HelperService::getCurrentOwner()->xlsforms->map(function (Xlsform $record) {
                            return $record->getDraftSubmissions();
                        })
                            ->reduce(fn(int $carry, int $item) => $carry + $item, 0);

                        $livewire->resetTable();

                        Notification::make('update_success')
                            ->title('Success!')
                            ->body("{$count} submissions have been pulled from the ODK server. (they may take a moment to process).")
                            ->color('success')
                            ->send();
                    }),

            ])
            ->bulkActions([]);
    }
}
