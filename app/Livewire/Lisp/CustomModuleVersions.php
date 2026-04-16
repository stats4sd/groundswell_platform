<?php

namespace App\Livewire\Lisp;

use App\Exports\LocalIndicatorXlsformQuestions\CustomIndicatorExport;
use App\Models\Holpa\LocalIndicator;
use App\Models\Team;
use App\Services\HelperService;
use Exception;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Application;
use Illuminate\Support\HtmlString;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Stats4sd\FilamentOdkLink\Listeners\HandleXlsformTemplateAdded;

class CustomModuleVersions extends Component implements HasForms
{
    use InteractsWithForms;

    public Team $team;

    public ?array $data;

    /** @var Collection<LocalIndicator> */
    public Collection $unmatchedLocalIndicators;

    public function mount(): void
    {
        $this->team = HelperService::getCurrentOwner();
        $this->unmatchedLocalIndicators = $this->team->localIndicators()->where('global_indicator_id', null)->get();

        $this->form->fill($this->team->toArray());
    }

    public function downloadTemplate()
    {
        return Excel::download(new CustomIndicatorExport($this->team), 'Custom Questions Template.xlsx');
    }

    public function form(Form $form): Form
    {
        return $form
            ->model($this->team)
            ->statePath('data')
            ->schema([
                Fieldset::make('Import Questions')
                    ->columns(6)
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('custom_questions_file')
                            ->columnSpan(5)
                            ->collection('custom_questions')
                            ->downloadable()
                            ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel']) // Accept only Excel files
                            ->maxSize(10240)
                            ->preserveFilenames()
                            ->label(
                                fn($state) => count($state) === 0
                                    ? 'Upload your completed Xlsform file with the custom questions for your indicators.'
                                    : 'To upload a new set of questions, delete the existing file with the "x" icon below and upload the new completed file.'
                            )
                            ->helperText(fn(self $livewire) => new HtmlString('<span class="text-red-700">' . collect($livewire->getErrorBag()->get('local_indicator_list'))->join('<br/>') . '</span>')),
                        Actions::make([
                            Actions\Action::make('save_file')
                                ->extraAttributes(['class' => 'buttona'])
                                ->label('Save File')
                                ->action(fn(Get $get) => $this->uploadFile($get('custom_questions_file')))
                                ->disabled(fn(Get $get) => ! collect($get('custom_questions_file'))->first() instanceof TemporaryUploadedFile),
                        ])
                            ->extraAttributes(['class' => 'flex justify-center']),
                    ]),
            ]);
    }

    public function uploadFile($customQuestionList)
    {
        // Check that a file is uploaded
        if (empty($customQuestionList) || ! is_array($customQuestionList)) {
            $this->addError('local_indicator_list', 'Please upload a file before proceeding.');

            return;
        }

        $this->dispatch('XlsformModuleVersionProcessing');

        $file = reset($customQuestionList);

        // follow process for importing XlsformTemplates
        $handler = new HandleXlsformTemplateAdded();

        $moduleVersions = $this->unmatchedLocalIndicators->map(fn(LocalIndicator $localIndicator) => $localIndicator->xlsformModuleVersion);

        try {
            foreach ($moduleVersions as $moduleVersion) {
                $handler->processXlsformTemplate($file->getRealPath(), $moduleVersion, 'indicator');
            }

            $this->team->addMedia($file)->toMediaCollection('custom_questions');

            // Display success message
            Notification::make()
                ->title('File uploaded successfully!')
                ->body('The file will be processed in the background and the questions will appear below once complete. You may leave this page without interrupting this process.') // TODO: listen for server-side event nad refresh the list of questions when the import jobs complete!
                ->success()
                ->send();
        } catch (Exception $e) {
            $this->addError('local_indicator_list', 'An error occurred while uploading the file.');
        }
    }

    public function render(): Factory|Application|View|\Illuminate\View\View|null
    {
        return view('livewire.lisp.custom-module-versions');
    }
}
