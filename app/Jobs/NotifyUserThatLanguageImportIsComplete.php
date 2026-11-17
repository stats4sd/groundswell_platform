<?php

namespace App\Jobs;

use App\Events\LanguageImportIsComplete;
use App\Models\User;
use App\Services\LocaleImportCounter;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Stats4sd\FilamentOdkLink\Concerns\NotifiesOnJobFailure;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Throwable;

class NotifyUserThatLanguageImportIsComplete implements ShouldQueue
{
    use NotifiesOnJobFailure;
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public Locale $locale, public XlsformTemplate $xlsformTemplate, public User $user)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // mark xlsform_module_versions as no longer needing a locale update
        $this->xlsformTemplate->xlsform_default_module_versions->each(function (XlsformModuleVersion $xlsformModuleVersion) {
            $xlsformModuleVersion->locales()
                ->sync([$this->locale->id => ['needs_update' => 0]], detaching: false);

        });

        // mark xlsform as needing draft update
        $xlsform = $this->locale->creator->xlsforms->filter(fn (Xlsform $xlsform) => $xlsform->xlsform_template_id === $this->xlsformTemplate->id)->first();

        if ($xlsform) {
            $xlsform->draft_needs_update = true;
            $xlsform->save();
        }

        Notification::make()
            ->title('Translation Import Complete')
            ->body("The import for the translations of {$this->xlsformTemplate->title} {$this->locale->description} is complete.")
            ->success()
            ->broadcast($this->user)
            ->send();

        LanguageImportIsComplete::dispatch($this->locale->id, $this->xlsformTemplate->id);

        // released last, so an import that fails part-way is released once, by failed(), and a retry never releases twice.
        app(LocaleImportCounter::class)->finished($this->locale);
    }

    public function failed(?Throwable $exception = null): void
    {
        app(LocaleImportCounter::class)->finished($this->locale);

        $this->notifyJobFailure(
            "Translation import could not be finalised: {$this->xlsformTemplate->title} {$this->locale->description}",
            $exception,
            $this->user,
        );
    }
}
