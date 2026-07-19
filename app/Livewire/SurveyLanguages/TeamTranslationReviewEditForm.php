<?php

namespace App\Livewire\SurveyLanguages;

use App\Imports\TranslationUploadInspector;
use App\Imports\XlsformTemplateLanguageImport;
use App\Jobs\NotifyUserThatLanguageImportIsComplete;
use App\Models\Team;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Stats4sd\FilamentOdkLink\Exports\XlsformTemplateTranslationsExport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

class TeamTranslationReviewEditForm extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public array $data;

    public Locale $locale;

    public Team $team;

    public bool $canSave = false;

    public bool $canMaintain = false;

    public function mount()
    {
        $this->form->fill($this->locale->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->model($this->locale)
            ->columns(2)
            ->schema(
                fn (): array => $this->team->xlsforms->map(fn (Xlsform $xlsform) => $xlsform->xlsformTemplate)
                    ->map(
                        fn (XlsformTemplate $xlsformTemplate) => Section::make($xlsformTemplate->title)
                            ->schema([
                                Actions::make([

                                    // download existing translations if they exist
                                    Action::make("download_existing_{$xlsformTemplate->id}")
                                        ->label(t('Download existing translations'))
                                        ->extraAttributes(['class' => 'buttona w-full'])
                                        ->action(fn () => Excel::download(
                                            new XlsformTemplateTranslationsExport($xlsformTemplate, $this->locale, withExistingStrings: true, owner: $this->team),
                                            "{$xlsformTemplate->title} translation - {$this->locale->language_label}.xlsx",
                                        )),

                                    // download blank template if needed
                                    Action::make("download_empty_{$xlsformTemplate->id}")
                                        ->label(t('Download empty translation template'))
                                        ->extraAttributes(['class' => 'buttona w-full'])
                                        ->visible(fn () => $this->locale->is_editable)
                                        ->action(fn () => Excel::download(
                                            new XlsformTemplateTranslationsExport($xlsformTemplate, $this->locale, withExistingStrings: false, owner: $this->team),
                                            "{$xlsformTemplate->title} translation template - {$this->locale->language_label}.xlsx",
                                        )),
                                ]),
                                SpatieMediaLibraryFileUpload::make('upload_for_template_'.$xlsformTemplate->id)
                                    ->collection('xlsform_template_translation_files')
                                    ->filterMediaUsing(fn (Collection $media) => $media->where('custom_properties.xlsform_template_id', $xlsformTemplate->id))
                                    ->customProperties(['xlsform_template_id' => $xlsformTemplate->id])
                                    ->visible(fn () => $this->locale->is_editable && $this->canMaintain)
                                    ->live()
                                    ->label(fn ($state) => blank($state)
                                        ? "Upload completed {$xlsformTemplate->title} translation file"
                                        : "To replace the translations, delete the existing file with the 'x' icon below and upload the new completed translations file."
                                    )
                                    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel']) // Accept only Excel files
                                    ->maxSize(10240)
                                    ->afterStateUpdated(function ($state) {
                                        if ($state instanceof TemporaryUploadedFile) {
                                            $this->enableSave();
                                        }

                                    }),
                            ])
                            ->columnSpan(1),
                    )->toArray(),
            );
    }

    public function submit(): void
    {
        if (! auth()->user()->can('maintain survey translations')) {
            abort(403);
        }

        $this->form->getState();
        $this->form->saveRelationships();

        $this->locale->refresh();

        $xlsformTemplates = $this->team->xlsforms
            ->map(fn (Xlsform $xlsform) => $xlsform->xlsformTemplate)
            ->unique('id');

        foreach ($xlsformTemplates as $xlsformTemplate) {
            $file = $this->locale->getMedia('xlsform_template_translation_files', function (Media $media) use ($xlsformTemplate) {
                return isset($media->custom_properties['xlsform_template_id']) && $media->custom_properties['xlsform_template_id'] === $xlsformTemplate->id;
            })->first();

            if (! $file) {
                continue;
            }

            $inspector = new TranslationUploadInspector($file->getPath());
            $errors = $inspector->validate($this->locale, $xlsformTemplate);

            if ($errors !== []) {
                $file->delete();

                Notification::make()
                    ->danger()
                    ->title(t('The translation file could not be processed'))
                    ->body(implode('<br/>', $errors))
                    ->persistent()
                    ->send();

                continue;
            }

            $this->locale->processing_count++;
            $this->locale->save();

            Excel::queueImport(new XlsformTemplateLanguageImport(
                $this->locale,
                $xlsformTemplate,
                auth()->user(),
                $inspector->textColumnIndex($this->locale),
            ), $file->getPath())
                ->chain([
                    new NotifyUserThatLanguageImportIsComplete($this->locale, $xlsformTemplate, request()->user()),
                ]);
        }

        $this->dispatch('closeModal');
    }

    public function duplicate(): void
    {
        if (! auth()->user()->can('maintain survey translations')) {
            abort(403);
        }

        // copy this locale as a new locale model
        $newRecord = $this->locale->replicate();
        $newRecord->description = $this->locale->languageLabel.' - duplicated';
        $newRecord->is_default = false;
        $newRecord->creator()->associate($this->team);
        $newRecord->save();

        // copy this locale's language strings to the new locale model
        foreach ($this->locale->languageStrings as $languageString) {
            $newLanguageString = $languageString->replicate();
            $newLanguageString->locale_id = $newRecord->id;
            $newLanguageString->save();
        }

        $this->dispatch('closeModal');
    }

    public function cancel(): void
    {
        $this->dispatch('closeModal');
    }

    public function enableSave(): void
    {
        $this->canSave = true;
    }

    public function render()
    {
        return view('livewire.survey-languages.team-translation-review-edit-form');
    }
}
