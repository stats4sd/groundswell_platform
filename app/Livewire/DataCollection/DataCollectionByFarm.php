<?php

namespace App\Livewire\DataCollection;

use App\Models\Team;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Str;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Resources\Components\Tab;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Attributes\Reactive;
use Livewire\Attributes\Url;
use Livewire\Component;

class DataCollectionByFarm extends Component implements HasTable, HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;
    use InteractsWithTable;

    #[Reactive]
    public bool $visible = false;

    #[Url]
    public ?array $tableFilters = null;

    public Team $team;

    public function render()
    {
        return view('livewire.data-collection.data-collection-by-farm');
    }

    public function table(Table $table): Table
    {

        $locationLevel = $this->team->locationLevels()->where('has_farms', true)->first();

        return $table
            ->heading('Farms')
            ->relationship(fn() => $this->team->farms())
            ->filters([
                SelectFilter::make('parent_' . $locationLevel->id)
                ->label('Location')
                ->relationship('location', 'name', fn($query) => $query->where('location_level_id', $locationLevel->id)),
            ])
            ->columns([
                ColumnGroup::make('Location', [
                    TextColumn::make('location.name')->label(Str::of($locationLevel->name)->title()),
                    TextColumn::make('identifying_attribute')->label('Farm Name'),
                ]),
                ColumnGroup::make('Surveys Completed', [
                    IconColumn::make('household_form_completed')->boolean(),
                    IconColumn::make('fieldwork_form_completed')->boolean(),
                ])
            ]);

    }
}
