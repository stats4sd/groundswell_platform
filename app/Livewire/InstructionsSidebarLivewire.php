<?php

namespace App\Livewire;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Contracts\View\Factory;
use Illuminate\Foundation\Application;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;
use Livewire\Component;

class InstructionsSidebarLivewire extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public string|HtmlString $heading;

    public string|HtmlString $instructions;

    public string|HtmlString $instructionsmarkcomplete;

    public function render(): Factory|Application|\Illuminate\Contracts\View\View|View|null
    {
        return view('livewire.instructions-sidebar-livewire');
    }

    public function showInstructionsAction(): Action
    {
        return Action::make('showInstructions')
            ->label('Instructions')
            ->icon('heroicon-o-information-circle')
            ->extraAttributes(['class' => '!shadow-none !font-bold pl-4 pr-8 py-6 !text-sm '])
            ->color('none')
            ->modalHeading($this->heading)
            ->modalContent(new HtmlString($this->instructions))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->slideOver();
    }
}
