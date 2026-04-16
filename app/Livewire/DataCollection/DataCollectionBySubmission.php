<?php

namespace App\Livewire\DataCollection;

use App\Models\Team;
use App\Services\HelperService;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;

class DataCollectionBySubmission extends Component implements HasTable, HasActions, HasForms
{

    use InteractsWithTable;
    use InteractsWithForms;
    use InteractsWithActions;

    public Team $team;

    public function mount(): void
    {
        $this->team = HelperService::getCurrentOwner();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Submission::query())
            ->columns([
                // TextColumn::make('farm.location.name')->label(fn(Submission $record) => $record->farms())...
                TextColumn::make('primaryDataSubject.team_code')->label('Farm Code'),
                TextColumn::make('primaryDataSubject.identifiers.name')->label('Farm Name'),
                TextColumn::make('xlsformVersion.xlsform.title')->label('Xlsform'),
                TextColumn::make('xlsformVersion.version')->label('Xlsform Version'),
                TextColumn::make('survey_started_at')->label('Survey Start Time'),

            ]);
    }


    public function render()
    {
        return view('livewire.data-collection.data-collection-by-submission');
    }
}
