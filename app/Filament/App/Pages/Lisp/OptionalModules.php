<?php

namespace App\Filament\App\Pages\Lisp;

use Filament\Navigation\NavigationItem;
use Filament\Pages\Page;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;

class OptionalModules extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $navigationLabel = 'Optional Modules';

    protected static ?string $title = 'Localisation: Optional Modules';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.app.pages.lisp.optional-modules';

    public static function getNavigationItems(): array
    {
        return [
            NavigationItem::make(static::getNavigationLabel())
                ->group('Optional Modules')
                ->icon(static::getNavigationIcon())
                ->isActiveWhen(fn () => request()->routeIs(static::getRouteName()))
                ->sort(static::getNavigationSort())
                ->url(static::getUrl()),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                XlsformModuleVersion::query()
                    ->whereNull('xlsform_module_id')
                    ->whereNull('owner_id')
            )
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_default')
                    ->boolean()
                    ->label('Default Version'),
                TextColumn::make('survey_rows_count')
                    ->counts('surveyRows')
                    ->label('# Questions'),
            ])
            ->paginated(false);
    }
}
