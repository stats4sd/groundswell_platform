<?php

namespace App\Filament\Shared;

use App\Services\HelperService;
use Filament\Actions\Action;

trait WithCompletionStatusBar
{
    public function markCompleteAction(): Action
    {
        return Action::make('markComplete')
            ->label('MARK AS COMPLETE')
            ->extraAttributes(['class' => 'buttona mx-4 inline-block'])
            ->action(function () {

                HelperService::getCurrentOwner()->update([
                    $this->completionProp => 1,
                ]);
            });
    }

    public function markIncompleteAction(): Action
    {
        return Action::make('markIncomplete')
            ->label('MARK AS INCOMPLETE')
            ->extraAttributes(['class' => 'buttona mx-4 inline-block'])
            ->action(function () {
                HelperService::getCurrentOwner()->update([
                    $this->completionProp => 0,
                ]);
            });
    }

}
