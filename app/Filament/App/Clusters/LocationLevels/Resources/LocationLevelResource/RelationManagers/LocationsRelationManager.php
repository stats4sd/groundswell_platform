<?php

namespace App\Filament\App\Clusters\LocationLevels\Resources\LocationLevelResource\RelationManagers;

use Exception;
use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use App\Services\HelperService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\RelationManagers\RelationManager;

class LocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'locations';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return Str::of($ownerRecord->name)->title().' List';
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('parent_id')
                    ->label(fn () => $this->getOwnerRecord()->parent->name)
                    ->relationship('parent', 'name', function ($query) {
                        $parent_location_level_id = $this->getOwnerRecord()->parent->id;
                        $query->where('location_level_id', $parent_location_level_id);
                    })
                    ->required()
                    ->searchable()
                    ->preload()
                    ->visible(fn () => $this->getOwnerRecord()->parent !== null),

                // location name should be uniqeu per team, as other teams may have the same location name.
                // ignore the current record to allow user update current record with same location name
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true, modifyRuleUsing: function (Unique $rule) {
                        return $rule->where('owner_id', HelperService::getCurrentOwner()->id);
                    })
                    ->maxLength(255),

                // location code should be unique per team, as other teams may have the same location code
                // ignore the current record to allow user update current record with same location code
                Forms\Components\TextInput::make('code')
                    ->required()
                    ->unique(ignoreRecord: true, modifyRuleUsing: function (Unique $rule) {
                        return $rule->where('owner_id', HelperService::getCurrentOwner()->id);
                    })
                    ->maxLength(255),

                Forms\Components\Hidden::make('owner_id')
                    ->default(HelperService::getCurrentOwner()->id)
            ])
            ->columns(1);
    }

    /**
     * @throws Exception
     */
    public function table(Table $table): Table
    {
        $columns = [];
        $filters = [];

        if ($this->getOwnerRecord()->parent) {
            $columns[] = Tables\Columns\TextColumn::make('parent.name')->label(fn () => $this->getOwnerRecord()->parent->name)->sortable();
            $filters[] = Tables\Filters\SelectFilter::make('parent')
                ->label(fn () => $this->getOwnerRecord()->parent->name)
                ->relationship('parent', 'name', fn (Builder $query) => $query->where('location_level_id', $this->getOwnerRecord()->parent->id));
        }

        $columns[] = Tables\Columns\TextColumn::make('name')->label($this->getOwnerRecord()->name);
        $columns[] = Tables\Columns\TextColumn::make('code');

        $columns[] = Tables\Columns\TextColumn::make('farms_all_count')
            ->label('# of Farms');

        return $table
            ->recordTitleAttribute('name')
            ->columns($columns)
            ->filters($filters)
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(fn () => 'Add new '.$this->getOwnerRecord()->name),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    #[On('echo:xlsforms,LocationImportCompleted')]
    public function refreshTable(): void
    {
       $this->resetTable();
    }

}
