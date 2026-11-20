<?php

namespace App\Filament\App\Pages\PlaceAdaptations;

use Filament\Support\Enums\Width;
use App\Filament\App\Pages\SurveyDashboard;
use App\Filament\Shared\WithCompletionStatusBar;
use App\Jobs\DeployTeamDraftForms;
use App\Models\Team;
use App\Services\HelperService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Throwable;

/**
 * @property-read Collection<int, Xlsform> $xlsforms
 */
class InitialPilot extends Page implements HasTable, HasInfolists, HasActions
{
    use InteractsWithTable;
    use InteractsWithForms;
    use InteractsWithActions;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.app.pages.place-adaptations.initial-pilot';

    public static function canAccess(): bool
    {
        return auth()->user()->can('view initial pilot');
    }
    public function getTitle(): string
    {
        return t('Initial Pilot');
    }

    public function getHeading(): string
    {
        return t('Survey Testing - Initial Pilot');
    }
    // protected ?string $subheading = "Test with local researchers and practitioners to review the initial localisations";

    public Team $team;

    /** @var array<int, int> */
    #[Locked]
    public array $activeFormIds = [];

    public function mount(): void
    {
        $this->team = HelperService::getCurrentOwner();
        $this->activeFormIds = $this->activeFormIdsNow();
    }

    /** @return Collection<int, Xlsform> */
    #[Computed]
    public function xlsforms(): Collection
    {
        return $this->team->freshXlsforms();
    }

    /**
     * Polled while a form is being updated: tells the user when a form they were waiting for is ready.
     */
    public function refreshForms(): void
    {
        unset($this->xlsforms);

        $activeIds = $this->activeFormIdsNow();
        $finishedIds = array_diff($this->activeFormIds, $activeIds);

        foreach ($this->xlsforms->whereIn('id', $finishedIds) as $xlsform) {
            if ($xlsform->draftState() === Xlsform::DRAFT_STATE_CURRENT) {
                Notification::make()
                    ->title(t('Test form updated'))
                    ->body($xlsform->title)
                    ->success()
                    ->send();
            }
        }

        $this->activeFormIds = $activeIds;
    }

    /** @return array<int, int> */
    private function activeFormIdsNow(): array
    {
        return $this->xlsforms
            ->filter(fn (Xlsform $xlsform): bool => $xlsform->hasActiveClaim())
            ->pluck('id')
            ->values()
            ->all();
    }

    private function canStartUpdate(): bool
    {
        return $this->updateBlockedReason() === null;
    }

    private function updateBlockedReason(): ?string
    {
        if ($this->xlsforms->contains(fn (Xlsform $xlsform): bool => $xlsform->hasActiveClaim())) {
            return t('A form is still updating. Please wait until it has finished.');
        }

        if (! $this->xlsforms->contains(fn (Xlsform $xlsform): bool => $xlsform->draftIsOutdated())) {
            return t('The test forms are up to date.');
        }

        return null;
    }

    private function updateTestForms(): void
    {
        if (! auth()->user()->can('maintain initial pilot')) {
            abort(403);
        }

        unset($this->xlsforms);

        $blockedReason = $this->updateBlockedReason();

        if ($blockedReason !== null) {
            Notification::make()
                ->title(t('The test forms were not updated'))
                ->body($blockedReason)
                ->warning()
                ->send();

            return;
        }

        $claimedForms = $this->team->claimDraftForms();

        if ($claimedForms->isEmpty()) {
            Notification::make()
                ->title(t('The test forms were not updated'))
                ->body(t('Another update has just started. Please wait until it has finished.'))
                ->warning()
                ->send();

            return;
        }

        $claimedIds = $claimedForms->pluck('id')->all();

        try {
            DeployTeamDraftForms::dispatch($this->team, $claimedIds, auth()->user());
        } catch (Throwable $exception) {
            $claimedForms->each(fn (Xlsform $xlsform) => $xlsform->releaseClaim(needsUpdate: true));

            report($exception);

            Notification::make()
                ->title(t('The test forms could not be updated'))
                ->body(t('The update could not be started. Please try again in a moment.'))
                ->danger()
                ->send();

            return;
        }

        $this->activeFormIds = array_values(array_unique([...$this->activeFormIds, ...$claimedIds]));
        unset($this->xlsforms);

        Notification::make()
            ->title(t('Updating the test forms'))
            ->body(t('This may take a few minutes. You can leave this page; the update will continue.'))
            ->success()
            ->send();
    }

    public function getBreadcrumbs(): array
    {
        return [
            SurveyDashboard::getUrl() => t('Survey Dashboard'),
            PlaceAdaptationsIndex::getUrl() => t('Place-based adaptations'),
            static::getUrl() => static::getTitle(),
        ];
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('update-test-forms')
                ->label(fn () => t('Update test forms'))
                ->icon('heroicon-o-arrow-path')
                ->visible(fn () => auth()->user()->can('maintain initial pilot'))
                ->disabled(fn () => ! $this->canStartUpdate())
                ->tooltip(fn () => $this->updateBlockedReason())
                ->requiresConfirmation()
                ->modalHeading(fn () => t('Update test forms'))
                ->modalDescription(fn () => t('The test forms will be rebuilt with the latest changes. Test submissions on the current test forms will be reset.'))
                ->action(fn () => $this->updateTestForms()),
        ];
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
            ->heading(fn () => t('Draft Submissions'))
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
            ->recordActions([
                Action::make('view')
                    ->modalContent(fn(Submission $record) => view('filament.app.pages.submissions.modal_view', ['submission' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(fn () => t('Close')),
            ])
            ->headerActions([
                Action::make('test-on-odk-central')
                    ->label(fn () => t('Test on ODK Central'))
                    ->url(fn() => HelperService::getCurrentOwner()->odkProject?->odk_url),
                Action::make('pull-submissions')
                    ->label(fn () => t('Manually Get Submissions'))
                    ->visible(fn () => auth()->user()->can('maintain initial pilot'))
                    ->action(function (self $livewire) {
                        if (!auth()->user()->can('maintain initial pilot')) {
                            abort(403);
                        }

                        $count = HelperService::getCurrentOwner()->xlsforms->map(function (Xlsform $record) {
                            return $record->getDraftSubmissions();
                        })
                            ->reduce(fn(int $carry, int $item) => $carry + $item, 0);

                        $livewire->resetTable();

                        Notification::make('update_success')
                            ->title(t('Success!'))
                            ->body($count . ' ' . t('submissions have been pulled from the ODK server. (they may take a moment to process).'))
                            ->color('success')
                            ->send();
                    }),

            ])
            ->toolbarActions([]);
    }
}
