<?php

namespace App\Jobs;

use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\HtmlString;
use Maatwebsite\Excel\Events\ImportFailed;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

class NotifyUserThatLanguageImportIsFailed implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(public Locale $locale, public XlsformTemplate
    $xlsformTemplate, public User $user, public string $message)
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


        Notification::make()
            ->title('Translation Import Failed')
            ->body(new HtmlString(
                "The import for the translations of {$this->xlsformTemplate->title} {$this->locale->description} failed:<br/><br/>

                {$this->message}
")
            )
            ->danger()
            ->broadcast($this->user)
            ->persistent()
            ->send();

        \App\Events\LanguageImportIsComplete::dispatch($this->locale->id, $this->xlsformTemplate->id, $this->user->id);
    }
}
