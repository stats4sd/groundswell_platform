<?php

namespace App\Filament\App\Clusters\LocationLevels\Resources\FarmEntityResource\Pages;

use App\Filament\App\Clusters\LocationLevels\Resources\FarmEntityResource;
use App\Imports\FarmEntityImport;
use App\Imports\LocationImport;
use App\Models\Import;
use App\Models\SampleFrame\FarmEntity;
use App\Models\SampleFrame\Location;
use App\Models\SampleFrame\LocationLevel;
use App\Services\HelperService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;

// ODK-Entities-backed counterpart to FarmResource\Pages\ImportLocationsAndFarms - the
// column-mapping wizard is identical (it's about parsing a spreadsheet + location
// hierarchy, independent of storage backend). Only the farm half of save() differs:
// FarmEntityImport instead of FarmImport. Locations stay fully local either way. Reuses
// the same Blade view as the original page - it's generic form+actions boilerplate.
class ImportLocationsAndFarmEntities extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = FarmEntityResource::class;

    protected string $view = 'filament.app.clusters.location-levels.resources.farm-resource.pages.import-locations-and-farms';

    public function getTitle(): string
    {
        return t('Import Locations and Farm List');
    }

    public ?array $data = [];

    protected ?string $disk = null;

    protected function getDisk()
    {
        return $this->disk ?: config('filesystems.default');
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // the uploaded excel file will be stored with the import model for locations;
        // copy it as a duplicate, which will be stored with the import model for farms
        Storage::copy($data['upload'], $data['upload'].'_duplicate');

        if ($data['override'] === 'yes') {
            HelperService::getCurrentOwner()->locations()->delete();
        }

        $locationImport = Import::create([
            'team_id' => HelperService::getCurrentOwner()->id,
            'model_type' => Location::class,
        ]);

        $locationImport->addMedia(Storage::path($data['upload']))->toMediaCollection();
        $data['import_id'] = $locationImport->id;

        Excel::import(new LocationImport($data), $locationImport->getFirstMediaPath());

        // import farms as ODK Central entities
        $farmImport = Import::create([
            'team_id' => HelperService::getCurrentOwner()->id,
            'model_type' => FarmEntity::class,
        ]);

        $farmImport->addMedia(Storage::path($data['upload']).'_duplicate')->toMediaCollection();
        $data['import_id'] = $farmImport->id;

        Excel::import(new FarmEntityImport($data), $farmImport->getFirstMediaPath());

        Notification::make()
            ->title(t('Locations and farms are being imported.'))
            ->body(t('The file will be processed in the background and the data will appear below once complete. You may leave this page without interrupting this process.'))
            ->success()
            ->send();

        redirect(FarmEntityResource::getUrl('index'));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                Wizard::make([

                    // Step 1
                    Step::make(t('Upload your farm list excel file'))
                        ->schema([
                            FileUpload::make('upload')
                                ->label(t('Location Levels and Farm List Excel Data'))
                                ->helperText(t('Please make sure your data is in the first worksheet of the Excel file, and that the first row contains the column headers.'))
                                ->disk($this->getDisk())
                                ->columns()
                                ->required()
                                ->live()
                                ->preserveFilenames()
                                ->afterStateUpdated(function ($state, Set $set) {
                                    if (! $state instanceof TemporaryUploadedFile) {
                                        return;
                                    }

                                    $headings = (new HeadingRowImport)->toArray($state->getRealPath());

                                    // $headings is an array(sheets) of arrays(headers)
                                    // We only want the first sheet
                                    $headings = $headings[0][0];

                                    $set('header_columns', $headings ?? []);
                                }),
                        ]),

                    // Step 2
                    Step::make(t('Map columns to location levels'))
                        ->schema(
                            [
                                Section::make(t('Column Mapping'))
                                    ->columns(2)
                                    ->schema(function ($livewire) {
                                        $hasFarmLevel = LocationLevel::where('has_farms', 1)->first();
                                        $currentLevel = $hasFarmLevel;
                                        $parents = collect([]);

                                        while ($currentLevel->parent) {
                                            $parents->push($currentLevel->parent);
                                            $currentLevel = $currentLevel->parent;
                                        }

                                        $parentQuestions = $parents->reverse()->map(callback: function ($parent) {
                                            return collect([
                                                Select::make("parent_{$parent->id}_code_column")
                                                    ->label(t('Which column contains the').' '.$parent->name.' '.t('unique code?'))
                                                    ->options(fn (Get $get) => $get('header_columns'))
                                                    ->notIn(['na'])
                                                    ->required(),
                                                Select::make("parent_{$parent->id}_name_column")
                                                    ->label(t('Which column contains the').' '.$parent->name.' '.t('name?'))
                                                    ->options(fn (Get $get) => $get('header_columns'))
                                                    ->notIn(['na'])
                                                    ->required(),
                                            ]);
                                        })->flatten();

                                        $currentLevelQuestions = collect([
                                            Select::make('code_column')
                                                ->label(t('Which column contains the').' '.$hasFarmLevel->name.' '.t('unique code?'))
                                                ->options(fn (Get $get) => $get('header_columns'))
                                                ->notIn(['na'])
                                                ->required(),
                                            Select::make('name_column')
                                                ->label(t('Which column contains the').' '.$hasFarmLevel->name.' '.t('name?'))
                                                ->options(fn (Get $get) => $get('header_columns'))
                                                ->notIn(['na'])
                                                ->required(),
                                        ]);

                                        return $parentQuestions->merge($currentLevelQuestions)->toArray();
                                    }),

                                Select::make('override')
                                    ->label(t('Do you want to replace all locations with this import? (This will delete all existing locations from all location levels!)'))
                                    ->options([
                                        'no' => t('No'),
                                        'yes' => t('Yes'),
                                    ])
                                    ->helperText(t('If you select "No", all existing locations will be kept. If you select "Yes", all existing locations will be deleted and replaced with the data from this import.'))
                                    ->default('no'),

                                Hidden::make('header_columns')
                                    ->default(['na' => '~~upload a file to see the headers~~'])
                                    ->live(),

                                Hidden::make('level')
                                    ->default(LocationLevel::where('has_farms', 1)->first()),

                                Hidden::make('user_id')
                                    ->default(fn () => auth()->id()),

                                Hidden::make('owner_id')
                                    ->default(HelperService::getCurrentOwner()->id),
                            ]
                        ),

                    // Step 3
                    Step::make(t('Map columns to farm'))
                        ->schema([

                            Hidden::make('header_columns')
                                ->default(['na' => '~~upload a file to see the column headers~~'])
                                ->live(),

                            Section::make(t('Location'))
                                ->schema([
                                    Select::make('location_level_id')
                                        ->label(t('Which location level are the farms linked to?'))
                                        ->options(
                                            LocationLevel::where('has_farms', true)->get()->pluck('name', 'id')
                                        )
                                        ->placeholder(t('Select a location level'))
                                        ->helperText(t('For many sampling strategies, this will be obvious (the lowest level). It may be less obvious when there are different hierarchies of locations in different places.'))
                                        ->live(),

                                    Select::make('location_code_column')
                                        ->options(fn (Get $get) => $get('header_columns'))
                                        ->label(fn (Get $get) => t('Which column contains the').' '.(LocationLevel::find($get('location_level_id'))?->name ?? t('location')).' '.t('unique code?'))
                                        ->placeholder(t('Select a column')),
                                ]),

                            Section::make(t('Farm Information'))
                                ->columns(1)
                                ->schema([
                                    Select::make('farm_code_column')
                                        ->label(t('Which column contains the farm unique code?'))
                                        ->placeholder(t('Select a column'))
                                        ->helperText(t('e.g. farm_id or farm_code'))
                                        ->live()
                                        ->options(fn (Get $get) => $get('header_columns')),

                                    CheckboxList::make('farm_identifiers')
                                        ->label(t('Are there any additional columns that contain identifiers for the farm? Tick all that apply.'))
                                        ->helperText(t('For example: family name, farm name, telephone numbers, etc. These are columns that can be useful for enumerators or project team members to identify the farm, but that should not be shared outside the project for data protection purposes.'))
                                        ->options(fn (Get $get): array => $get('header_columns'))
                                        ->disableOptionWhen(
                                            fn (string $value, Get $get): bool => $value === (string) $get('farm_code_column') ||
                                                collect($get('farm_properties'))->contains($value) ||
                                                $value === 'na'
                                        )
                                        ->live()
                                        ->columnSpanFull(),

                                    CheckboxList::make('farm_properties')
                                        ->label(t('Are there any additional columns that contain properties of the farm? Tick all that apply.'))
                                        ->helperText(t('These are not identifiers, but are properties of the farm that are useful for analysis. For example: size of the farm, year of first engagement, etc. These are columns that can potentially be shared outside the project for analysis purposes.'))
                                        ->options(fn (Get $get) => $get('header_columns'))
                                        ->disableOptionWhen(
                                            fn (string $value, Get $get): bool => $value === (string) $get('farm_code_column') ||
                                                collect($get('farm_identifiers'))->contains($value) ||
                                                $value === 'na'
                                        )
                                        ->live()
                                        ->columnSpanFull(),

                                    Hidden::make('owner_id')
                                        ->default(HelperService::getCurrentOwner()->id),
                                ]),

                            Hidden::make('user_id')
                                ->default(auth()->id()),
                        ]),

                ]),

            ])->statePath('data');
    }
}
