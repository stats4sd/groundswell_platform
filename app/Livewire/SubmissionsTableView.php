<?php

namespace App\Livewire;

use App\Filament\App\Resources\SubmissionResource;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Reactive;
use Livewire\Component;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;
use Stats4sd\FilamentOdkLink\Services\HelperService;

class SubmissionsTableView extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public bool $test;


    #[Reactive]
    public bool $visible = false;

    public function render()
    {
        return view('livewire.submissions-table-view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(fn() => $this->test ? 'Pilot Test Submissions' : 'Survey Submissions')
            ->description(fn() => $this->test ? 'These are the submissions that have been submitted during the pilot test. You can review them here, even after the pilot is complete.' : '')
            ->groups([
                \Filament\Tables\Grouping\Group::make('xlsformVersion.xlsform.title')->label('Form'),
                Group::make('primary_data_subject_id')
                    ->label('Farm ID'),
            ])
            ->defaultGroup(\Filament\Tables\Grouping\Group::make('xlsformVersion.xlsform.title')->label(''))
            ->query(fn() => Submission::whereHas('xlsformVersion',
                fn($query) => $query->whereHas('xlsform',
                    fn($query) => $query->where('owner_id', HelperService::getCurrentOwner()->id)
                ))->where('test_data', $this->test)
            )
            ->columns([

                TextColumn::make('primaryDataSubject.location.name')->label('Farm Location'),
                TextColumn::make('primaryDataSubject.identifying_attribute')->label('Farm Name'),
                TextColumn::make('survey_started_at')->label('Submitted at'),
                TextColumn::make('if_updated_at')
                    ->label('Updated at'),

            ])
            ->actions([
                Action::make('view')
                    ->label('View Raw Data')
                    ->icon('heroicon-o-eye')
                    ->url(fn(Submission $record) => SubmissionResource::getUrl('view', ['record' => $record])),
            ])
            ->bulkActions([
                BulkAction::make('toggle_live')
                    ->label(function() {

                        $newType = $this->test === false ? 'pilot' : 'live';

                        return "Mark selected submissions as {$newType} data";
                    })
                    ->requiresConfirmation()
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        $records->each(function (Submission $record) {
                            $record->test_data = !$record->test_data;
                            $record->save();
                        });
                    }),
            ]);
    }
}
