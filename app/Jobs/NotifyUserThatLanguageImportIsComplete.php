<?php

namespace App\Jobs;

use App\Events\LanguageImportIsComplete;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

class NotifyUserThatLanguageImportIsComplete implements ShouldQueue
{
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

        // check if this is the last import for this Locale...
        $this->locale->processing_count--;
        $this->locale->save();

        // mark xlsform_module_versions as no longer needing a locale update
        $this->xlsformTemplate->xlsform_default_module_versions->each(function(XlsformModuleVersion $xlsformModuleVersion) {
          $xlsformModuleVersion->locales()
              ->sync([$this->locale->id => ['needs_update' => 0]], detaching: false);

        });

        // mark xlsform as needing draft update
        $xlsform = $this->locale->creator->xlsforms->filter(fn(Xlsform $xlsform) => $xlsform->xlsform_template_id === $this->xlsformTemplate->id)->first();

        $xlsform->draft_needs_update = true;
        $xlsform->save();

        Notification::make()
            ->title('Translation Import Complete')
            ->body("The import for the translations of {$this->xlsformTemplate->title} {$this->locale->description} is complete.")
            ->success()
            ->broadcast($this->user)
            ->send();

        LanguageImportIsComplete::dispatch($this->locale->id, $this->xlsformTemplate->id);
    }
}
