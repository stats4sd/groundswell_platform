<?php

namespace App\Filament\Admin\Resources\TeamResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use App\Filament\Admin\Resources\TeamResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class ViewTeam extends ViewRecord
{
    protected static string $resource = TeamResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('View on ODK Central')
                ->label('View on ODK Central')
                ->visible(fn () => $this->getRecord()->odkProject !== null)
                ->url(fn () => config('filament-odk-link.odk.url').'/#/projects/'.$this->getRecord()->odkProject->id),
            EditAction::make(),
            DeleteAction::make(),
        ];
    }
}
