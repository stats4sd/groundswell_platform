<?php

namespace App\Filament\Tables;

use App\Filament\App\Clusters\LocationLevels\Resources\ImportResource;
use App\Models\Import;
use App\Models\SampleFrame\FarmEntity;
use App\Models\SampleFrame\Location;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * The one column definition behind both ImportResource's table and RecentImportsWidget, so the
 * history page and the widget that points at it cannot drift apart.
 */
class ImportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(fn () => t('Started'))
                    ->dateTime()
                    ->sortable(),

                // NOT ->make('model_type')->formatStateUsing(): Import::modelType() shadows the
                // raw column on read and would hand this a pluralised short name to match on.
                TextColumn::make('model_type')
                    ->label(fn () => t('Imported'))
                    ->state(fn (Import $record): string => self::modelTypeLabel($record->getRawOriginal('model_type'))),

                TextColumn::make('file_name')
                    ->label(fn () => t('File'))
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->label(fn () => t('Status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::statusLabel($state))
                    ->color(fn (string $state): string => match ($state) {
                        'failed' => 'danger',
                        'complete' => 'success',
                        'stale' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('error_count')
                    ->label(fn () => t('Problems'))
                    ->placeholder('—'),

                // Deliberately plain-escaped - no ->html() and no HtmlString anywhere near it.
                // These messages are built from spreadsheet cell contents.
                TextColumn::make('error_preview')
                    ->label(fn () => t('First problem'))
                    ->state(fn (Import $record): ?string => $record->error_lines->first()['messages'][0] ?? null)
                    ->wrap()
                    ->limit(120)
                    ->placeholder('—'),

                TextColumn::make('user.name')
                    ->label(fn () => t('Imported by'))
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Import $record): string => ImportResource::getUrl('view', ['record' => $record]))
            // an import that is running while the user watches should stop saying "pending"
            ->poll('15s');
    }

    public static function modelTypeLabel(?string $modelType): string
    {
        return match ($modelType) {
            Location::class => t('Locations'),
            FarmEntity::class => t('Farms'),
            default => (string) Str::of((string) $modelType)->afterLast('\\')->plural(),
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'failed' => t('Failed'),
            'complete' => t('Complete'),
            'stale' => t('May have been interrupted'),
            default => t('In progress'),
        };
    }
}
