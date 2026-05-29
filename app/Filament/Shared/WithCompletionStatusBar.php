<?php

namespace App\Filament\Shared;

use App\Services\HelperService;
use Filament\Actions\Action;

trait WithCompletionStatusBar
{
    public function markCompleteAction(): Action
    {
        return Action::make('markComplete')
            ->label(fn () => t('MARK AS COMPLETE'))
            ->color('gray')
            ->extraAttributes(['class' => 'buttonbrown mx-4 inline-block fi-ac-action-no-style'])
            ->action(function () {

                HelperService::getCurrentOwner()->update([
                    $this->completionProp => 1,
                ]);
            });
    }

    public function markIncompleteAction(): Action
    {
        return Action::make('markIncomplete')
            ->label(fn () => t('MARK AS INCOMPLETE'))
            ->color('gray')
            ->extraAttributes(['class' => 'buttonbrown mx-4 inline-block fi-ac-action-no-style'])
            ->action(function () {
                HelperService::getCurrentOwner()->update([
                    $this->completionProp => 0,
                ]);
            });
    }

}
