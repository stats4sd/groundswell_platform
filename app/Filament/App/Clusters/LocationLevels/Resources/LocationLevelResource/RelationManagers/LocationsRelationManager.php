<?php

namespace App\Filament\App\Clusters\LocationLevels\Resources\LocationLevelResource\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Hidden;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Exception;
use Filament\Forms;
use Filament\Tables;
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
        return Str::of($ownerRecord->name)->title() . ' ' . t('List');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('parent_id')
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
                TextInput::make('name')
                    ->label(t('Name'))
                    ->required()
                    ->unique(ignoreRecord: true, modifyRuleUsing: function (Unique $rule) {
                        return $rule->where('owner_id', HelperService::getCurrentOwner()->id);
                    })
                    ->maxLength(255),

                // location code should be unique per team, as other teams may have the same location code
                // ignore the current record to allow user update current record with same location code
                TextInput::make('code')
                    ->label(t('Code'))
                    ->required()
                    ->unique(ignoreRecord: true, modifyRuleUsing: function (Unique $rule) {
                        return $rule->where('owner_id', HelperService::getCurrentOwner()->id);
                    })
                    ->maxLength(255),

                Hidden::make('owner_id')
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
            $columns[] = TextColumn::make('parent.name')->label(fn () => $this->getOwnerRecord()->parent->name)->sortable();
            $filters[] = SelectFilter::make('parent')
                ->label(fn () => $this->getOwnerRecord()->parent->name)
                ->relationship('parent', 'name', fn (Builder $query) => $query->where('location_level_id', $this->getOwnerRecord()->parent->id));
        }

        $columns[] = TextColumn::make('name')->label($this->getOwnerRecord()->name);
        $columns[] = TextColumn::make('code');

        $columns[] = TextColumn::make('farms_all_count')
            ->label(fn () => t('# of Farms'));

        return $table
            ->recordTitleAttribute('name')
            ->columns($columns)
            ->filters($filters)
            ->headerActions([
                CreateAction::make()
                    ->label(fn () => t('Add new') . ' ' . $this->getOwnerRecord()->name),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    #[On('echo:xlsforms,LocationImportCompleted')]
    public function refreshTable(): void
    {
       $this->resetTable();
    }

}
