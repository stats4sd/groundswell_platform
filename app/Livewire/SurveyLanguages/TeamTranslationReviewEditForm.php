<?php

namespace App\Livewire\SurveyLanguages;

use App\Imports\TranslationUploadInspector;
use App\Imports\XlsformTemplateLanguageImport;
use App\Jobs\NotifyUserThatLanguageImportIsComplete;
use App\Models\Team;
use App\Services\LocaleImportCounter;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Stats4sd\FilamentOdkLink\Exports\XlsformTemplateTranslationsExport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\XlsformModuleVersionLocale;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Throwable;

class TeamTranslationReviewEditForm extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public array $data;

    public Locale $locale;

    public Team $team;

    public bool $canSave = false;

    public bool $canMaintain = false;

    /** @var array<int, array{title: string, count: int}> */
    public array $overwriteWarnings = [];

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
        $this->processUploads(confirmed: false);
    }

    public function confirmOverwrite(): void
    {
        $this->processUploads(confirmed: true);
    }

    public function cancelOverwrite(): void
    {
        $this->overwriteWarnings = [];
    }

    private function processUploads(bool $confirmed): void
    {
        if (! auth()->user()->can('maintain survey translations')) {
            abort(403);
        }

        $this->form->validate();

        $blankCounts = [];

        foreach ($this->xlsformTemplates() as $xlsformTemplate) {
            $blankCounts[$xlsformTemplate->id] = $this->inspectPendingUploads($xlsformTemplate);
        }

        $blankCounts = array_filter($blankCounts, fn (?int $count) => $count !== null);

        $warnings = [];

        foreach ($blankCounts as $templateId => $count) {
            if ($count > 0) {
                $warnings[$templateId] = [
                    'title' => $this->xlsformTemplates()->firstWhere('id', $templateId)->title,
                    'count' => $count,
                ];
            }
        }

        if ($warnings !== []) {
            if (! $confirmed || $this->hasMoreBlankCellsThanShown($warnings)) {
                $this->overwriteWarnings = $warnings;

                return;
            }
        }

        $this->overwriteWarnings = [];

        $this->importNewUploads();

        $this->dispatch('closeModal');
    }

    /**
     * Validates the files that were just uploaded for the template and drops the invalid ones from the form.
     * Returns how many cells would erase an existing translation, or null when nothing valid is waiting for it.
     */
    private function inspectPendingUploads(XlsformTemplate $xlsformTemplate): ?int
    {
        $stateKey = "upload_for_template_{$xlsformTemplate->id}";

        $blankCount = null;

        foreach ($this->data[$stateKey] ?? [] as $uuid => $upload) {
            if (! $upload instanceof TemporaryUploadedFile) {
                continue;
            }

            $inspection = $this->inspectUpload($upload, $xlsformTemplate);

            if ($inspection['errors'] !== []) {
                $upload->delete();

                unset($this->data[$stateKey][$uuid]);

                $this->keepExistingUploadFor($xlsformTemplate);

                Notification::make()
                    ->danger()
                    ->title(t('The translation file could not be processed'))
                    ->body(implode('<br/>', $inspection['errors']))
                    ->persistent()
                    ->send();

                continue;
            }

            $blankCount = ($blankCount ?? 0) + $inspection['blankCount'];
        }

        return $blankCount;
    }

    private function keepExistingUploadFor(XlsformTemplate $xlsformTemplate): void
    {
        $stateKey = "upload_for_template_{$xlsformTemplate->id}";

        $existingFiles = $this->locale->getMedia('xlsform_template_translation_files', function (Media $media) use ($xlsformTemplate) {
            return isset($media->custom_properties['xlsform_template_id']) && $media->custom_properties['xlsform_template_id'] === $xlsformTemplate->id;
        });

        foreach ($existingFiles as $existingFile) {
            $this->data[$stateKey][$existingFile->uuid] = $existingFile->uuid;
        }
    }

    /** @return array{errors: array<int, string>, blankCount: int} */
    private function inspectUpload(TemporaryUploadedFile $upload, XlsformTemplate $xlsformTemplate): array
    {
        $localCopyName = 'translation-upload-checks/'.Str::uuid().'.xlsx';

        Storage::disk('local')->put($localCopyName, $upload->get());

        try {
            $inspector = new TranslationUploadInspector(Storage::disk('local')->path($localCopyName));
            $errors = $inspector->validate($this->locale, $xlsformTemplate);

            return [
                'errors' => $errors,
                'blankCount' => $errors === [] ? $inspector->blankOverExistingCount($this->locale, $xlsformTemplate) : 0,
            ];
        } finally {
            Storage::disk('local')->delete($localCopyName);
        }
    }

    /** @param array<int, array{title: string, count: int}> $warnings */
    private function hasMoreBlankCellsThanShown(array $warnings): bool
    {
        foreach ($warnings as $templateId => $warning) {
            if ($warning['count'] > ($this->overwriteWarnings[$templateId]['count'] ?? 0)) {
                return true;
            }
        }

        return false;
    }

    private function importNewUploads(): void
    {
        $this->locale->refresh();

        $existingMediaIds = $this->locale->getMedia('xlsform_template_translation_files')->pluck('id');

        $this->form->saveRelationships();

        $this->locale->refresh();

        $newFiles = $this->locale->getMedia('xlsform_template_translation_files')
            ->reject(fn (Media $media) => $existingMediaIds->contains($media->id));

        $imports = [];

        foreach ($newFiles as $file) {
            $xlsformTemplate = $this->xlsformTemplates()
                ->firstWhere('id', $file->custom_properties['xlsform_template_id'] ?? null);

            if (! $xlsformTemplate) {
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

            $imports[] = [$xlsformTemplate, $file, $inspector];
        }

        // count every import before queueing any, so a fast worker can never finish one before it has been counted.
        $counter = app(LocaleImportCounter::class);
        $counter->started($this->locale, count($imports));

        foreach ($imports as [$xlsformTemplate, $file, $inspector]) {
            try {
                Excel::queueImport(new XlsformTemplateLanguageImport(
                    $this->locale,
                    $xlsformTemplate,
                    auth()->user(),
                    $inspector->textColumnIndex($this->locale),
                ), $file->getPath())
                    ->chain([
                        new NotifyUserThatLanguageImportIsComplete($this->locale, $xlsformTemplate, request()->user()),
                    ]);
            } catch (Throwable $exception) {
                report($exception);

                $counter->finished($this->locale);

                Notification::make()
                    ->danger()
                    ->title(t('The translation file could not be processed'))
                    ->body(t('The import could not be started. Please try again, or contact support if the problem persists.'))
                    ->persistent()
                    ->send();
            }
        }

        $this->locale->refresh();
    }

    public function duplicate(): void
    {
        if (! auth()->user()->can('maintain survey translations')) {
            abort(403);
        }

        try {
            DB::transaction(function (): void {
                $newRecord = $this->locale->replicate();
                $newRecord->description = $this->locale->languageLabel.' - duplicated';
                $newRecord->is_default = false;
                $newRecord->processing_count = 0;
                $newRecord->creator()->associate($this->team);
                $newRecord->save();

                foreach ($this->locale->languageStrings as $languageString) {
                    $newLanguageString = $languageString->replicate();
                    $newLanguageString->locale_id = $newRecord->id;
                    $newLanguageString->save();
                }

                $this->copyModuleVersionLinks($newRecord);
                $this->copyTranslationFiles($newRecord);
            });
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->danger()
                ->title(t('The translation could not be duplicated'))
                ->body(t('Please try again, or contact support if the problem persists.'))
                ->persistent()
                ->send();

            return;
        }

        $this->dispatch('closeModal');
    }

    public function cancel(): void
    {
        $this->dispatch('closeModal');
    }

    /**
     * The status accessor treats a module version marked "needs update" as outstanding work, so the
     * copy has to carry the same flags over the defaults it was given when it was created.
     */
    private function copyModuleVersionLinks(Locale $newLocale): void
    {
        $newLocale->xlsformModuleVersions()->sync(
            $this->locale->xlsformModuleVersionLocales
                ->mapWithKeys(fn (XlsformModuleVersionLocale $link) => [
                    $link->xlsform_module_version_id => ['needs_update' => $link->needs_update],
                ])
                ->all(),
        );
    }

    /**
     * A locale counts as translated once it holds an uploaded file for every template the team uses.
     * Team locales have those files to copy; a default locale gets its strings from the imported
     * template instead, so the equivalent file is generated from the strings just copied over.
     */
    private function copyTranslationFiles(Locale $newLocale): void
    {
        if (! $this->locale->is_default) {
            $this->locale->getMedia('xlsform_template_translation_files')
                ->each(fn (Media $media) => $media->copy($newLocale, 'xlsform_template_translation_files'));

            return;
        }

        foreach ($this->xlsformTemplates() as $xlsformTemplate) {
            $temporaryPath = 'translation-duplicates/'.Str::uuid().'.xlsx';

            Excel::store(
                new XlsformTemplateTranslationsExport($xlsformTemplate, $newLocale, withExistingStrings: true, owner: $this->team),
                $temporaryPath,
                'local',
            );

            $newLocale
                ->addMedia(Storage::disk('local')->path($temporaryPath))
                ->usingFileName("{$xlsformTemplate->title} translation - {$newLocale->language_label}.xlsx")
                ->withCustomProperties(['xlsform_template_id' => $xlsformTemplate->id])
                ->toMediaCollection('xlsform_template_translation_files');
        }
    }

    /** @return Collection<int, XlsformTemplate> */
    private function xlsformTemplates(): Collection
    {
        return $this->team->xlsforms
            ->map(fn (Xlsform $xlsform) => $xlsform->xlsformTemplate)
            ->unique('id')
            ->values();
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
