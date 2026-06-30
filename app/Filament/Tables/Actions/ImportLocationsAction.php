<?php

namespace App\Filament\Tables\Actions;

use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use RuntimeException;
use App\Models\Import;
use App\Models\SampleFrame\Location;
use App\Services\HelperService;
use Closure;
use EightyNine\ExcelImport\ExcelImportAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;

class ImportLocationsAction extends ExcelImportAction
{
    // Installed filament excel import package "eightynine/filament-excel-import": "3.x-dev",
    // ExcelImportAction class is a simplifed one.
    // Copy below code segment to make it working well

    // Code segment belongs to superclass ExcelImportAction starts here...

    protected ?string $disk = null;

    protected function getDisk()
    {
        return $this->disk ?: config('filesystems.default');
    }

    // Code segment belongs to superclass ExcelImportAction ends here...

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->modalWidth('3xl')
            ->modalHeading(fn () => t('Import Locations'))
            ->modalDescription(fn () => t('Import Locations from an Excel file. The first worksheet of the Excel file should contain the data to import. The first row of the worksheet should contain the column headings.'));
    }

    protected function getDefaultForm(): array
    {
        return [
            FileUpload::make('upload')
                ->label(fn ($livewire) => str($livewire->getRecord()->name)->plural()->title().' '.t('Excel Data'))
                ->disk($this->getDisk())
                ->columns()
                ->required()
                ->live()
                ->afterStateUpdated(function (?TemporaryUploadedFile $state, Set $set) {
                    $headings = (new HeadingRowImport)->toArray($state->getRealPath());

                    // $headings is an array(sheets) of arrays(headers)
                    // We only want the first sheet
                    $headings = $headings[0][0];

                    $set('header_columns', $headings ?? []);
                }),

            Section::make(t('Column Mapping'))
                ->columns(2)
                ->schema(function ($livewire) {
                    $currentLevel = $livewire->getRecord();
                    $parents = collect([]);

                    while ($currentLevel->parent) {
                        $parents->push($currentLevel->parent);
                        $currentLevel = $currentLevel->parent;
                    }

                    $parentQuestions = $parents->reverse()->map(callback: function ($parent) {
                        return collect([
                            Select::make("parent_{$parent->id}_code_column")
                                ->label(t('Which column contains the') . ' ' . $parent->name . ' ' . t('unique code?'))
                                ->options(fn (Get $get) => $get('header_columns'))
                                ->notIn(['na'])
                                ->required(),
                            Select::make("parent_{$parent->id}_name_column")
                                ->label(t('Which column contains the') . ' ' . $parent->name . ' ' . t('name?'))
                                ->options(fn (Get $get) => $get('header_columns'))
                                ->notIn(['na'])
                                ->required(),
                        ]);
                    })->flatten();

                    $currentLevelQuestions = collect([
                        Select::make('code_column')
                            ->label(fn ($livewire) => t('Which column contains the') . ' ' . $livewire->getRecord()->name . ' ' . t('unique code?'))
                            ->options(fn (Get $get) => $get('header_columns'))
                            ->notIn(['na'])
                            ->required(),
                        Select::make('name_column')
                            ->label(fn ($livewire) => t('Which column contains the') . ' ' . $livewire->getRecord()->name . ' ' . t('name?'))
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
                ->default(['na' => t('Upload a file to see the headers')])
                ->live(),
            Hidden::make('level')
                ->default(fn ($livewire) => $livewire->getRecord()),
            Hidden::make('user_id')
                ->default(fn () => auth()->id()),
            Hidden::make('owner_id')
                ->default(HelperService::getCurrentOwner()->id),
        ];
    }

    public function action(Closure|string|null $action): static
    {
        if ($action !== 'importData') {
            throw new RuntimeException('You\'re unable to override the action for this plugin');
        }

        $this->action = $this->importData();

        return $this;
    }

    private function importData(): Closure
    {
        return function (array $data, $livewire): bool {

            if ($data['override'] === 'yes') {
                HelperService::getCurrentOwner()->locations()->delete();
            }

            $import = Import::create([
                'team_id' => HelperService::getCurrentOwner()->id,
                'model_type' => Location::class,
            ]);

            $import->addMedia(Storage::path($data['upload']))->toMediaCollection();

            $data['import_id'] = $import->id;

            $importObject = new $this->importClass($data);

            Excel::import($importObject, $import->getFirstMediaPath());

            return true;
        };
    }
}
