<?php

namespace App\Filament\App\Clusters\LocationLevels\Resources;

use App\Filament\App\Clusters\LocationLevels;
use App\Filament\App\Clusters\LocationLevels\Resources\ImportResource\Pages\ListImports;
use App\Filament\App\Clusters\LocationLevels\Resources\ImportResource\Pages\ViewImport;
use App\Filament\Tables\ImportsTable;
use App\Models\Import;
use App\Models\SampleFrame\FarmEntity;
use App\Models\SampleFrame\Location;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The team's own history of location and farm spreadsheet imports. Before this existed,
 * imports.errors was written by four code paths and read by none: a failed import told the user
 * only that their file "will be processed in the background", and then nothing further.
 */
class ImportResource extends Resource
{
    protected static ?string $model = Import::class;

    protected static ?string $slug = 'imports';

    protected static ?string $cluster = LocationLevels::class;

    protected static ?string $tenantOwnershipRelationshipName = 'team';

    protected static ?string $tenantRelationshipName = 'imports';

    protected static ?int $navigationSort = 100;

    public static function getModelLabel(): string
    {
        return t('Import');
    }

    public static function getPluralModelLabel(): string
    {
        return t('Past Imports');
    }

    public static function getNavigationLabel(): string
    {
        return t('Past Imports');
    }

    public static function table(Table $table): Table
    {
        return ImportsTable::configure($table)
            // file_name reads the record's media, and user.name the importer
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['media', 'user']))
            ->filters([
                SelectFilter::make('model_type')
                    ->label(fn () => t('Imported'))
                    ->options([
                        Location::class => t('Locations'),
                        FarmEntity::class => t('Farms'),
                    ]),

                // status is derived from errors/success/created_at rather than stored, so the
                // filter has to reproduce that derivation in SQL instead of matching a column.
                SelectFilter::make('status')
                    ->label(fn () => t('Status'))
                    ->options([
                        'failed' => t('Failed'),
                        'complete' => t('Complete'),
                        'pending' => t('In progress'),
                        'stale' => t('May have been interrupted'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $withoutErrors = fn (Builder $query): Builder => $query->where(
                            fn (Builder $query) => $query->whereNull('errors')->orWhereJsonLength('errors', 0)
                        );

                        return match ($data['value'] ?? null) {
                            'failed' => $query->whereNotNull('errors')->whereJsonLength('errors', '>', 0),
                            'complete' => $withoutErrors($query)->where('success', true),
                            'pending' => $withoutErrors($query)->where('success', false)->where('created_at', '>=', now()->subHour()),
                            'stale' => $withoutErrors($query)->where('success', false)->where('created_at', '<', now()->subHour()),
                            default => $query,
                        };
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(fn () => t('Import details'))
                ->columns(2)
                ->schema([
                    TextEntry::make('model_type')
                        ->label(fn () => t('Imported'))
                        ->state(fn (Import $record): string => ImportsTable::modelTypeLabel($record->getRawOriginal('model_type'))),
                    TextEntry::make('file_name')
                        ->label(fn () => t('File'))
                        ->placeholder('—'),
                    TextEntry::make('user.name')
                        ->label(fn () => t('Imported by'))
                        ->placeholder('—'),
                    TextEntry::make('status')
                        ->label(fn () => t('Status'))
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => ImportsTable::statusLabel($state))
                        ->color(fn (string $state): string => match ($state) {
                            'failed' => 'danger',
                            'complete' => 'success',
                            'stale' => 'warning',
                            default => 'gray',
                        }),
                    TextEntry::make('created_at')
                        ->label(fn () => t('Started'))
                        ->dateTime(),
                    TextEntry::make('finished_at')
                        ->label(fn () => t('Finished'))
                        ->dateTime()
                        ->placeholder('—'),
                ]),

            Section::make(fn () => t('What went wrong'))
                ->description(fn () => t('Each entry names the row and column of the spreadsheet that could not be imported.'))
                ->visible(fn (Import $record): bool => self::attributedErrorLines($record) !== [])
                ->schema([
                    RepeatableEntry::make('attributed_error_lines')
                        ->hiddenLabel()
                        ->state(fn (Import $record): array => self::attributedErrorLines($record))
                        ->columns(3)
                        ->schema([
                            TextEntry::make('row')
                                ->label(fn () => t('Row'))
                                ->placeholder('—'),
                            TextEntry::make('attribute')
                                ->label(fn () => t('Column'))
                                ->placeholder('—'),
                            // ->html() sanitises the message (Str::sanitizeHtml) before it is
                            // rendered. NEVER wrap one of these in an HtmlString to make markup
                            // work: CanFormatState::formatState() hands an Htmlable straight
                            // through with no sanitisation, and these strings are built from
                            // spreadsheet cell contents.
                            TextEntry::make('messages')
                                ->label(fn () => t('Problem'))
                                ->listWithLineBreaks()
                                ->bulleted()
                                ->html(),
                        ]),
                ]),

            // Anything the importer could not attribute to a row: a raw exception message (a
            // QueryException's is a whole SQL statement with its bindings) or the explanation
            // written onto a farm import that was skipped because its location import failed.
            // Reachable, but not the first thing on the page - unless it is all there is.
            Section::make(fn () => t('Technical details'))
                ->collapsible()
                ->collapsed(fn (Import $record): bool => self::attributedErrorLines($record) !== [])
                ->visible(fn (Import $record): bool => self::unattributedErrorLines($record) !== [])
                ->schema([
                    TextEntry::make('unattributed_error_lines')
                        ->hiddenLabel()
                        ->state(fn (Import $record): array => collect(self::unattributedErrorLines($record))
                            ->flatMap(fn (array $line): array => $line['messages'])
                            ->all())
                        ->listWithLineBreaks()
                        ->bulleted()
                        ->html(),
                ]),
        ])
            ->columns(1);
    }

    /** @return list<array{row: ?int, attribute: ?string, messages: non-empty-list<string>}> */
    private static function attributedErrorLines(Import $import): array
    {
        return $import->error_lines
            ->reject(fn (array $line): bool => self::isUnattributed($line))
            ->values()
            ->all();
    }

    /** @return list<array{row: ?int, attribute: ?string, messages: non-empty-list<string>}> */
    private static function unattributedErrorLines(Import $import): array
    {
        return $import->error_lines
            ->filter(fn (array $line): bool => self::isUnattributed($line))
            ->values()
            ->all();
    }

    /** @param array{row: ?int, attribute: ?string, messages: non-empty-list<string>} $line */
    private static function isUnattributed(array $line): bool
    {
        if ($line['row'] !== null) {
            return false;
        }

        return $line['attribute'] === null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImports::route('/'),
            'view' => ViewImport::route('/{record}'),
        ];
    }
}
