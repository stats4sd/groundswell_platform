<?php

namespace App\Livewire;

use App\Mail\RegisterInterestEmail;
use App\Mail\RegisterInterestEmailResponse;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Callout;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

class CoverPage extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public function render(): Factory|Application|View|\Illuminate\View\View|null
    {
        return view('livewire.cover-page');
    }

    public function registerInterestAction(): Action
    {
        return Action::make('registerInterest')
            ->extraAttributes(['class' => 'button bg-orange hover:bg-white b-white border-2 rounded-full px-4 py-2 text-white hover:text-orange font-semibold w-auto flex justify-center items-center text-center px-4 mx-2'])
            ->label(t('Register Interest'))
            ->schema([
                Callout::make()
                    ->info()
                    ->description(t('Please fill in your details below - your email address will be used to contact you.')),
                TextInput::make('email')->required()
                    ->email()
                    ->label(t('Enter your email address')),
                TextInput::make('name')
                    ->label(t('Enter your name')),
                Textarea::make('organisation')
                    ->label(t('Enter your organisation name')),
                Textarea::make('details')
                    ->rows(5)
                    ->label(t('Do you intend to implement Groundswell International Surveys? If so, please give some details about your project / work, etc.')),
            ])
            ->action(function (array $data) {

                Mail::to(config('mail.to.support'))->send(new RegisterInterestEmail($data));
                Mail::to($data['email'])->send(new RegisterInterestEmailResponse($data));

                Notification::make('success')
                    ->title(t('Thank you'))
                    ->body(t('Thank you for your interest in Groundswell International Surveys. You should receive an automated email to confirm your registration of interest.'))
                    ->send();
            });

    }


}
