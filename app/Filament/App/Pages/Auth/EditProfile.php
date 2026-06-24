<?php

namespace App\Filament\App\Pages\Auth;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Component;
use Exception;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Callout;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithOdkCentralAccount;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

class EditProfile extends \Filament\Auth\Pages\EditProfile
{

    protected static ?string $navigationLabel = 'My Account';
    protected static ?string $navigationUri = '/my-account';

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected ?string $heading = 'My Account';

    public static function getNavigationLabel(): string
    {
        return t('My Account');
    }

    public function getHeading(): string
    {
        return t('My Account');
    }

    public static function canAccess(): bool
    {
        return auth()->user()->can('view my account');
    }


    /**
     * @throws Exception
     */
    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->visible(fn () => auth()->user()->can('maintain my account')),
            $this->getCancelFormAction()
                ->visible(fn () => auth()->user()->can('maintain my account')),
        ];
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        Section::make(fn () => t('Profile Information'))
                            ->schema([
                                $this->getNameFormComponent()
                                    ->disabled(fn () => !auth()->user()->can('maintain my account')),
                                $this->getEmailFormComponent()
                                    ->disabled(fn () => !auth()->user()->can('maintain my account')),
                            ]),
                        Section::make(fn () => t('Change Password'))
                            ->columns(1)
                            ->schema([
                                Callout::make(fn () => t('Password info'))
                                    ->info()
                                    ->description(fn () => t('To change your password, please first enter your current password, then the new password. You may leave the password fields blank if you do not wish to change your password.')),
                                $this->getCurrentPasswordFormComponent()
                                    ->disabled(fn () => !auth()->user()->can('maintain my account')),
                                $this->getPasswordFormComponent()
                                    ->disabled(fn () => !auth()->user()->can('maintain my account')),
                                $this->getPasswordConfirmationFormComponent()
                                    ->disabled(fn () => !auth()->user()->can('maintain my account')),
                            ]),
                    ])
                    ->operation('edit')
                    ->model($this->getUser())
                    ->statePath('data')
                    ->inlineLabel(! static::isSimple()),
            ),
        ];
    }

    protected function getCurrentPasswordFormComponent(): Component
    {
        // user should only enter current password when changing to a new password
        return TextInput::make('current_password')
            ->label(fn () => t('Current Password'))
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            // current password is required when new password is entered
            ->requiredWith('password')
            ->rule('current_password')
            ->autocomplete('current-password');
    }

    protected function getPasswordFormComponent(): Component
    {
        // user
        return TextInput::make('password')
            ->label(__('filament-panels::pages/auth/edit-profile.form.password.label'))
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            // new password is required when current password is entered
            ->requiredWith('current_password')
            ->rule(Password::default())
            ->autocomplete('new-password')
            ->minLength(10)
            ->same('passwordConfirmation');
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return TextInput::make('passwordConfirmation')
            ->label(__('filament-panels::pages/auth/edit-profile.form.password_confirmation.label'))
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            // new password for confirmation is required when new password is entered
            ->requiredWith('password')
            ->dehydrated(false);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {

        $data['password_plain'] = $data['password'];
        $data['password'] = Hash::make($data['password']);

        return $data;
    }

    /**
     * @throws RequestException
     * @throws BindingResolutionException
     * @throws ConnectionException
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (!auth()->user()->can('maintain my account')) {
            abort(403);
        }

        $updateData = [
            'email' => $data['email'],
            'name' => $data['name'],
        ];

        $record->update($updateData);

        // we should check password_plain content.
        // password contentn is encrypted, it always has content even password_plain is empty.
        if (isset($data['password_plain']) && $data['password_plain'] !== '') {

            $record->update(['password' => $data['password']]);

            if ($record instanceof WithOdkCentralAccount) {
                $odkLinkService = app()->make(OdkLinkService::class);

                $odkLinkService->updateUserPassword($record, $data['current_password'], $data['password_plain']);
            }

            // TODO: clear user entered value in three password related fields
            $this->fillForm();
        }

        return $record;

    }
}
