<?php

namespace App\Filament\Admin\Resources\TeamResource\RelationManagers;

use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Maatwebsite\Excel\Facades\Excel;
use Stats4sd\FilamentOdkLink\Exports\XlsformExport\XlsformWorkbookExport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;

class XlsformsRelationManager extends RelationManager
{
    protected static string $relationship = 'xlsforms';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')
                    ->grow(false)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')->label('Live Status'),
                TextColumn::make('odk_published_at')->label('Published at'),
                TextColumn::make('odk_draft_updated_at')->label('Draft Updated at'),
                ViewColumn::make('team_datasets_required')
                    ->view('filament-odk-link::filament.tables.columns.team-datasets-required'),
                TextColumn::make('submissions_count')->counts('submissions')
                    ->label('No. of Submissions')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([])
            ->actions([
                Action::make('download xls file')
                    ->action(function (Xlsform $record) {

                        return Excel::download((new XlsformWorkbookExport($record)), 'xlsform.xlsx');

                    }),
                // add Publish button
                Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-m-arrow-up-tray')
                    ->requiresConfirmation()
                    ->action(function (Xlsform $record) {

                        // create draft if there is no draft yet
                        if (! $record->has_draft) {
                            $record->deployDraft(true);
                        }

                        if ($record->has_draft) {
                            $record->publishForm();
                        } else {
                            Notification::make('no_draft_deployed')
                                ->title('No Draft Deployed')
                                ->body("It looks like this form doesn't have a draft deployed yet. Please deploy a draft before publishing.s")
                                ->warning()
                                ->send();
                        }

                    }),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Xlsform to Team')
                    ->after(function (Xlsform $record) {
                        $record->refresh();
                        $record->deployDraft();
                    }),
            ]);
    }
}
