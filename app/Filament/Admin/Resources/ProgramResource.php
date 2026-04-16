<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ProgramResource\Pages;
use Filament\Tables\Table;
use Stats4sd\FilamentTeamManagement\Models\Program;

class ProgramResource extends \Stats4sd\FilamentTeamManagement\Filament\Admin\Resources\ProgramResource
{
    protected static ?string $model = Program::class;

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrograms::route('/'),
        ];
    }

    // override table from package
    public static function table(Table $table): Table
    {
        return parent::table($table)
            ->recordUrl(fn ($record) => url('program/'.$record->id));
    }
}
