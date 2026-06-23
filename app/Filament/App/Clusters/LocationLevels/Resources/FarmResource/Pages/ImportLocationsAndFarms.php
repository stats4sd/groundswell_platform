<?php

namespace App\Filament\App\Clusters\LocationLevels\Resources\FarmResource\Pages;

use App\Models\Import;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Form;
use App\Imports\FarmImport;
use Filament\Actions\Action;
use App\Imports\LocationImport;
use App\Services\HelperService;
use App\Models\SampleFrame\Farm;
use Filament\Resources\Pages\Page;
use App\Models\SampleFrame\Location;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Wizard;
use Filament\Support\Exceptions\Halt;
use Filament\Forms\Components\Section;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\HeadingRowImport;
use Filament\Notifications\Notification;
use App\Models\SampleFrame\LocationLevel;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Concerns\InteractsWithForms;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use App\Filament\App\Clusters\LocationLevels\Resources\FarmResource;

class ImportLocationsAndFarms extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = FarmResource::class;

    protected static string $view = 'filament.app.clusters.location-levels.resources.farm-resource.pages.import-locations-and-farms';

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

    // add a "Save Changes" button to submit the form
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
                ->submit('save'),
        ];
    }


    // add a function to handle the submitted form
    public function save(): void
    {
        // get the submitted form data for further processing
        $data = $this->form->getState();

        // the uploaded excel file will be stored with import model for locations
        // copy the uploaded excel file as a duplicate file, which will be stored with the import model for farms
        Storage::copy($data['upload'], $data['upload'] . '_duplicate');


        // import locations
        if ($data['override'] === 'yes') {
            HelperService::getCurrentOwner()->locations()->delete();
        }

        $locationImport = Import::create([
            'team_id' => HelperService::getCurrentOwner()->id,
            'model_type' => Location::class,
        ]);

        $locationImport->addMedia(Storage::path($data['upload']))->toMediaCollection();
        $data['import_id'] = $locationImport->id;

        // import locations
        Excel::import(new LocationImport($data), $locationImport->getFirstMediaPath());

        
        // import farms
        // create import record - for review and error tracking by users
        $farmImport = Import::create([
            'team_id' => HelperService::getCurrentOwner()->id,
            'model_type' => Farm::class,
        ]);

        $farmImport->addMedia(Storage::path($data['upload']) . '_duplicate')->toMediaCollection();
        $data['import_id'] = $farmImport->id;

        // import farms
        Excel::import(new FarmImport($data), $farmImport->getFirstMediaPath());

        // send notification
        Notification::make()
            ->title(t('Locations and farms are being imported.'))
            ->body(t('The file will be processed in the background and the data will appear below once complete. You may leave this page without interrupting this process.'))
            ->success()
            ->send();

        // redirect to farms list page
        redirect(FarmResource::getUrl('index'));
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([

                Wizard::make([

                    // Step 1
                    Wizard\Step::make(t('Upload your farm list excel file'))
                        ->schema([

                            // Question: is the file upload works for ExcelImportAction's subclass only?
                            FileUpload::make('upload')
                                ->label(t('Location Levels and Farm List Excel Data'))
                                ->helperText(t('Please make sure your data is in the first worksheet of the Excel file, and that the first row contains the column headers.'))
                                ->disk($this->getDisk())
                                ->columns()
                                ->required()
                                ->live()
                                ->preserveFilenames()
                                ->afterStateUpdated(function (?TemporaryUploadedFile $state, Set $set) {
                                    $headings = (new HeadingRowImport)->toArray($state?->getRealPath());

                                    // $headings is an array(sheets) of arrays(headers)
                                    // We only want the first sheet
                                    $headings = $headings[0][0];

                                    $set('header_columns', $headings ?? []);
                                }),

                        ]),


                    // Step 2
                    Wizard\Step::make(t('Map columns to location levels'))
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
                                                    ->label(t('Which column contains the') . ' ' . $parent->name . ' ' . t('unique code?'))
                                                    ->options(fn(Get $get) => $get('header_columns'))
                                                    ->notIn(['na'])
                                                    ->required(),
                                                Select::make("parent_{$parent->id}_name_column")
                                                    ->label(t('Which column contains the') . ' ' . $parent->name . ' ' . t('name?'))
                                                    ->options(fn(Get $get) => $get('header_columns'))
                                                    ->notIn(['na'])
                                                    ->required(),
                                            ]);
                                        })->flatten();

                                        $currentLevelQuestions = collect([
                                            Select::make('code_column')
                                                ->label(t('Which column contains the') . ' ' . $hasFarmLevel->name . ' ' . t('unique code?'))
                                                ->options(fn(Get $get) => $get('header_columns'))
                                                ->notIn(['na'])
                                                ->required(),
                                            Select::make('name_column')
                                                ->label(t('Which column contains the') . ' ' . $hasFarmLevel->name . ' ' . t('name?'))
                                                ->options(fn(Get $get) => $get('header_columns'))
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

                                // find the location level that has farms
                                // Question: can one team has more than one location levels that have farms?
                                Hidden::make('level')
                                    ->default(LocationLevel::where('has_farms', 1)->first()),

                                Hidden::make('user_id')
                                    ->default(fn() => auth()->id()),

                                Hidden::make('owner_id')
                                    ->default(HelperService::getCurrentOwner()->id),
                            ]
                        ),


                    // Step 3
                    Wizard\Step::make(t('Map columns to farm'))
                        ->schema([

                            Hidden::make('header_columns')
                                ->default(['na' => '~~upload a file to see the column headers~~'])
                                ->live(),

                            Section::make(t('Location'))
                                ->schema([

                                    // Question:
                                    // 1. can one team has more than one location levels that have farms?
                                    // 2. can we set default value to simplify the import process?
                                    Select::make('location_level_id')
                                        ->label(t('Which location level are the farms linked to?'))
                                        ->options(
                                            LocationLevel::where('has_farms', true)->get()->pluck('name', 'id')
                                        )
                                        ->placeholder(t('Select a location level'))
                                        ->helperText(t('For many sampling strategies, this will be obvious (the lowest level. It may be less obvious when there are different hierarchies of locations in different places.'))
                                        ->live(),

                                    Select::make('location_code_column')
                                        ->options(fn(Get $get) => $get('header_columns'))
                                        ->label(fn(Get $get) => t('Which column contains the') . ' ' . (LocationLevel::find($get('location_level_id'))?->name ?? t('location')) . ' ' . t('unique code?'))
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
                                        ->options(fn(Get $get) => $get('header_columns')),

                                    CheckboxList::make('farm_identifiers')
                                        ->label(t('Are there any additional columns that contain identifiers for the farm? Tick all that apply.'))
                                        ->helperText(t('For example: family name, farm name, telephone numbers, etc. These are columns that can be useful for enumerators or project team members to identify the farm, but that should not be shared outside the project for data protection purposes.'))
                                        ->options(fn(Get $get): array => $get('header_columns'))
                                        ->disableOptionWhen(
                                            fn(string $value, Get $get): bool => $value === (string)$get('farm_code_column') ||
                                                collect($get('farm_properties'))->contains($value) ||
                                                $value === 'na'
                                        )
                                        ->live()
                                        ->columnSpanFull(),

                                    CheckboxList::make('farm_properties')
                                        ->label(t('Are there any additional columns that contain properties of the farm? Tick all that apply.'))
                                        ->helperText(t('These are not identifiers, but are properties of the farm that are useful for analysis. For example: size of the farm, year of first engagement, etc. These are columns that can potentially be shared outside the project for analysis purposes.'))
                                        ->options(fn(Get $get) => $get('header_columns'))
                                        ->disableOptionWhen(
                                            fn(string $value, Get $get): bool => $value === (string)$get('farm_code_column') ||
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
