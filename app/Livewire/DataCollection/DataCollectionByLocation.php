<?php

namespace App\Livewire\DataCollection;

use App\Filament\App\Pages\DataCollection\MonitorDataCollection;
use App\Models\SampleFrame\Location;
use App\Models\SampleFrame\LocationLevel;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;
use Livewire\Attributes\Url;
use Livewire\Component;

class DataCollectionByLocation extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public ?int $locationLevelId;

    #[Reactive]
    public bool $visible = false;

    #[Url]
    public ?array $tableFilters = null;

    #[Computed]
    public function locationLevel()
    {
        return LocationLevel::find($this->locationLevelId);
    }

    public function table(Table $table): Table
    {
        $parentLocationLevel = $this->locationLevel?->parent;
        $filters = [];

        if ($parentLocationLevel) {
            $filters[] = SelectFilter::make('parent_'.$parentLocationLevel->id)
                ->relationship('parent', 'name', fn (Builder $query) => $query->where('location_level_id', $parentLocationLevel->id));
        }

        return $table
            ->relationship(fn () => $this->locationLevel->locations())
            ->heading(fn () => Str::of($this->locationLevel->name)->title()->plural())
            ->filters($filters)
            ->columns([
                ColumnGroup::make('Location', [
                    TextColumn::make('name')->label('')
                        ->url(fn (Location $record) => MonitorDataCollection::getUrl().'?'.http_build_query([
                            'locationLevelId' => $record->locationLevel->children->first()?->id,
                            'tableFilters' => [
                                'parent_'.$record->locationLevel->id => ['value' => $record->id],
                            ],
                        ])),
                ]),
                TextColumn::make('farms_all_count')->label('Farms'),
            ]);
    }

    public function render()
    {
        return view('livewire.data-collection.data-collection-by-location');
    }
}
