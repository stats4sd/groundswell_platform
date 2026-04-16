<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DietDiversityModuleVersionResource\Pages;
use App\Filament\Admin\Resources\DietDiversityModuleVersionResource\RelationManagers;
use App\Models\DietDiversityModuleVersion;
use Awcodes\Shout\Components\Shout;
use Filament\Forms;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Stats4sd\FilamentOdkLink\Models\Country;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;

class DietDiversityModuleVersionResource extends Resource
{
    protected static ?string $model = XlsformModuleVersion::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-europe-africa';

    protected static ?string $navigationGroup = 'ODK Forms and Datasets';
    protected static ?string $navigationLabel = 'Diet Diversity Module Versions';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('xlsformModule', function (Builder $query) {
                $query->where('xlsform_modules.name', 'diet_diversity');
            });
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Hidden::make('xlsform_module_id')
                    ->default(XlsformModule::where('name', 'diet_diversity')->first()?->id),
                Forms\Components\Select::make('country_id')
                    ->live()
                    ->searchable()
                    ->preload()
                    ->required()
                    ->relationship('country', 'name')
                    ->label('Which country is this Diet Diversity module for?')
                    ->afterStateUpdated(fn(Forms\Set $set, ?string $state) => $set('name', 'diet_diversity ' . Country::find($state)?->name)),
                Hidden::make('name'),
                Forms\Components\SpatieMediaLibraryFileUpload::make('xlsfile')
                    ->required()
                    ->label('Upload Xlsfile with the module questions.')
                    ->collection('xlsform_file')
                    ->preserveFilenames()
                    ->downloadable()
                    ->visible(fn(Forms\Get $get): bool => !$get('is_default'))
                    ->placeholder(__('File')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->sortable(),
                IconColumn::make('is_default')->sortable()
                    ->boolean(),
                TextColumn::make('country.name')->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDietDiversityModuleVersions::route('/'),
            'create' => Pages\CreateDietDiversityModuleVersion::route('/create'),
            'edit' => Pages\EditDietDiversityModuleVersion::route('/{record}/edit'),
        ];
    }
}
