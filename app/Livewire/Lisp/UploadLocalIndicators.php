<?php

namespace App\Livewire\Lisp;

use Exception;
use App\Models\Team;
use Filament\Forms\Get;
use Livewire\Component;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Livewire\WithFileUploads;
use App\Services\HelperService;
use Awcodes\TableRepeater\Header;
use Illuminate\Support\HtmlString;
use App\Models\Holpa\LocalIndicator;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\LocalIndicatorImport;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Actions;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Fieldset;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Awcodes\TableRepeater\Components\TableRepeater;
use Maatwebsite\Excel\Validators\ValidationException;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class UploadLocalIndicators extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;
    use WithFileUploads;

    public Team $team;
    public ?array $data;

    public function mount(): void
    {
        $this->team = HelperService::getCurrentOwner();

        $this->form->fill($this->team->toArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->model($this->team)
            ->schema([
                Fieldset::make('Upload Local Indicators')
                    ->columns(6)
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('local_indicator_list')
                            ->columnSpan(5)
                            ->collection('local_indicators')
                            ->downloadable()
                            ->label('')
                            ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel']) // Accept only Excel files
                            ->maxSize(10240)
                            ->preserveFilenames()
                            ->label(
                                fn($state) => count($state) === 0
                                    ? 'Upload your list of local indicators here.'
                                    : 'To upload a new set of indicators, delete the existing file with the "x" icon below and upload the new completed file.'
                            )
                            ->helperText(fn(self $livewire) => new HtmlString('<span class="text-red-700">' . collect($livewire->getErrorBag()->get('local_indicator_list'))->join('<br/>') . '</span>')),

                        Actions::make([
                            Actions\Action::make('save_file')
                                ->extraAttributes(['class' => ' buttona'])
                                ->label('Save File')
                                ->action(fn(Get $get) => $this->uploadFile($get('local_indicator_list')))
                                ->disabled(fn(Get $get) => ! collect($get('local_indicator_list'))->first() instanceof TemporaryUploadedFile),
                        ])
                            ->extraAttributes(['class' => 'flex justify-center']),
                    ]),
            ]);
    }

    public function saveIndicators(): void
    {
        $this->form->getState();
        $this->form->saveRelationships();
    }

    public function uploadFile($localIndicatorList): void
    {
        // Check that a file is uploaded
        if (empty($localIndicatorList) || !is_array($localIndicatorList)) {
            $this->addError('local_indicator_list', 'Please upload a file before proceeding.');

            return;
        }

        // Get the file from the array
        $file = reset($localIndicatorList);

        try {
            // Proceed with the import
            Excel::import(new LocalIndicatorImport($this->team), $file->getRealPath());

            $this->team->addMedia($file)->toMediaCollection('local_indicators');
            $this->team->refresh();
            $this->form->fill($this->team->toArray());


            // Display success message
            Notification::make()
                ->title('Success')
                ->body('File uploaded successfully!')
                ->success()
                ->send();
        } catch (ValidationException $e) {

            $errors = collect($e->validator->errors())
                ->map(function ($errors, $key) {
                    $keyRow = explode('.', $key)[0];
                    $keyColumn = explode('.', $key)[1];
                    $errors = collect($errors)->join('; ');

                    return "Row $keyRow, Column $keyColumn: $errors";
                });

            $this->addError('local_indicator_list', 'It looks like the file you uploaded is not valid. Please check the file and try again. Errors Found: <br/><br/>' . $errors->join('<br/>'));
        } catch (Exception $e) {
            $this->addError('local_indicator_list', 'An error occurred while uploading the file.');
        }
    }

    // the original table function shows the uploaded file name, upload date and Delete button (it looks like this table is not being used in application)
    // use table function to let user to maintain local indicator records
    public function table(Table $table): Table
    {
        return $table
            ->query(
                LocalIndicator::query()
                    ->where('team_id', auth()->user()->latestTeam->id),
            )
            ->heading('Local Indicators List')
            ->columns([
                TextColumn::make('name')
                    ->sortable(),
                TextColumn::make('domain.name'),
            ])
            ->actions([
                EditAction::make()
                    ->form([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('domain_id')
                            ->relationship('domain', 'name')
                            ->required(),
                    ]),
                DeleteAction::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->model(LocalIndicator::class)
                    ->label('ADD LOCAL INDICATOR')
                    ->form([
                        Hidden::make('team_id')
                            ->required()
                            ->default(auth()->user()->latestTeam->id),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('domain_id')
                            ->relationship('domain', 'name')
                            ->required(),
                    ])
            ])
            ->paginated(false);
    }

    public function render(): \Illuminate\Contracts\View\Factory|\Illuminate\Foundation\Application|\Illuminate\Contracts\View\View|\Illuminate\View\View|null
    {
        return view('livewire.lisp.upload-local-indicators');
    }
}
