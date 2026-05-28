<?php

namespace App\Filament\App\Pages\SurveyLanguages;

use App\Filament\App\Pages\SurveyDashboard;
use App\Models\Team;
use App\Services\HelperService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Stats4sd\FilamentOdkLink\Models\Country;

class SurveyCountry extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.app.pages.survey-languages.survey-country';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Survey Country & Languages';

    public Team $team;

    public array $formData = [];

    public static function canAccess(): bool
    {
        return auth()->user()->can('view select country and languages');
    }

    public function mount(): void
    {
        $this->team = HelperService::getCurrentOwner();
        $this->form->fill($this->team->toArray());
    }

    public function getTitle(): string
    {
        return t('Survey Country & Languages');
    }

    public function getBreadcrumbs(): array
    {
        return [
            SurveyDashboard::getUrl() => t('Survey Dashboard'),
            SurveyLanguagesIndex::getUrl() => t('Survey Languages'),
            static::getUrl() => t('Survey Country & Languages'),
        ];
    }

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::Full;
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('formData')
            ->model($this->team)
            ->schema([
                Select::make('country_id')
                    ->label(fn() => t('Country'))
                    ->relationship('country', 'name')
                    ->searchable()
                    ->preload()
                    ->disabled(fn () => !auth()->user()->can('maintain select country and languages'))
                    ->createOptionForm(fn () => [
                        // add validations
                        Select::make('region_id')
                            ->relationship('region', 'name')
                            ->label(fn() => t('Select the region for this country'))
                            ->required(),
                        TextInput::make('name')
                            ->label(fn() => t('Enter the name of this country'))
                            ->required()
                            ->unique()
                            ->maxLength(255),
                        TextInput::make('iso_alpha2')
                            ->label(fn() => t('Enter the ISO Alpha-2 code for this country'))
                            ->required()
                            ->unique()
                            ->maxLength(2),
                        TextInput::make('iso_alpha3')
                            ->label(fn() => t('Enter the ISO Alpha-3 code for this country'))
                            ->required()
                            ->unique()
                            ->maxLength(3),
                    ])
                    ->createOptionUsing(function (array $data): string {
                        // set iso_alpha3 as country record id
                        $data['id'] = $data['iso_alpha3'];

                        // create new country model
                        $newCountry = Country::create($data);

                        // new country record id is 0 now, return iso_alphas so the newly created country record will be selected automatically
                        return $data['iso_alpha3'];
                    })
                    ->afterStateUpdated(fn (self $livewire) => $livewire->saveData())
                    ->live(),
                Select::make('languages')
                    ->label(fn() => t('Languages'))
                    ->relationship(
                        'languages',
                        'name',
                        // TODO: setup dataset for link between language and country
                        // modifyQueryUsing: fn(Builder $query, Get $get) => $get('country_id') ? $query->whereHas('countries', fn(Builder $query) => $query->where('countries.id', $get('country_id'))) : $query
                    )
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->disabled(fn () => !auth()->user()->can('maintain select country and languages'))
                    ->afterStateUpdated(fn (self $livewire) => $livewire->saveData())
                    ->live(),

            ]);
    }

    public function saveData(): void
    {
        if (!auth()->user()->can('maintain select country and languages')) {
            abort(403);
        }

        $this->team->update($this->form->getState());
    }
}
