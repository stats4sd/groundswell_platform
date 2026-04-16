<?php

namespace App\Livewire;

use App\Exports\DataExport\FarmSurveyDataExport;
use Livewire\Component;
use Filament\Tables\Table;
use Livewire\Attributes\On;
use App\Services\HelperService;
use Illuminate\Support\HtmlString;
use Filament\Tables\Actions\Action;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Notifications\Notification;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Actions\Concerns\InteractsWithActions;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Maatwebsite\Excel\Facades\Excel;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

class XlsformsTableView extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public function render()
    {
        return view('livewire.xlsforms-table-view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->relationship(fn(): HasMany => HelperService::getCurrentOwner()->xlsforms())
            ->inverseRelationship('owner')
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')
                    ->grow(false),
                TextColumn::make('status')
                    ->color(fn($state) => match ($state) {
                        'LIVE' => 'success',
                        'DRAFT' => 'info',
                        default => 'light',
                    })
                    ->iconColor(fn($state) => match ($state) {
                        'LIVE' => 'success',
                        'DRAFT' => 'info',
                        default => 'light',
                    })
                    ->icon(fn($state) => match ($state) {
                        'LIVE' => 'heroicon-o-check',
                        'DRAFT' => 'heroicon-o-pencil',
                        default => 'heroicon-o-information-circle',
                    })
                    ->description(fn(Xlsform $record): ?HtmlString => $record->live_needs_update || $record->draft_needs_update ? new HtmlString('<span class="text-red-600">updates available to publish</span>') : null)
                    ->label('Status'),

                TextColumn::make('live_submissions_count')
                    ->label(fn() => new HtmlString('No. of Submissions <br/>in ODK Central')),
                TextColumn::make('submissions_count')
                    ->label(fn() => new HtmlString('No. of Submissions <br/>in database'))
                    ->counts('submissions'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Action::make('update_published_version')
                    ->visible(fn(Xlsform $record) => $record->live_needs_update || $record->draft_needs_update)
                    ->label('Publish changes')
                    ->extraAttributes(['class' => 'buttona text-white font-normal'])
                    ->action(function (Xlsform $record) {

                        $record->publishForm();

                        Notification::make('update_success')
                            ->title('Success!')
                            ->body("The form {$record->title} is being compiled and will be deployed shortly.")
                            ->color('success')
                            ->send();
                    }),

                Action::make('pull-submissions')
                    ->label('Manually Get Submissions')
                    ->action(function (Xlsform $record) {

                        $submissionCount = $record->getSubmissions();

                        $record->refresh();
                        Notification::make('update_started')
                            ->title('Form publishing started')
                            ->body("{$submissionCount} submissions have been pulled from the ODK server for the form {$record->title} (they may take a moment to process).")
                            ->persistent()
                            ->send();

                        // reset table to update the status
                        $this->resetTable();
                    }),
            ])
            ->headerActions([
                Action::make('download-submissions')
                    ->label('Download Submissions')
                    ->action(function () {
                        return Excel::download(new FarmSurveyDataExport(HelperService::getCurrentOwner()), 'submissions.xlsx');
                    }),

            ])
            ->bulkActions([]);
    }

    #[On('echo:xlsforms,.FilamentOdkLink.XlsformWasPublished')]
    public function handleXlsformPublished(): void
    {
        $this->resetTable();
        Notification::make('publish_success')
            ->title('Success!')
            ->body("The form has been published successfully.")
            ->color('success')
            ->send();
    }


}
