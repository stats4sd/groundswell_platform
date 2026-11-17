<?php

namespace App\Jobs;

use App\Events\LanguageImportIsComplete;
use App\Models\User;
use App\Services\LocaleImportCounter;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\HtmlString;
use Stats4sd\FilamentOdkLink\Concerns\NotifiesOnJobFailure;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Throwable;

class NotifyUserThatLanguageImportIsFailed implements ShouldQueue
{
    use NotifiesOnJobFailure;
    use Queueable;

    public int $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(public Locale $locale, public XlsformTemplate $xlsformTemplate, public User $user, public string $message)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Notification::make()
            ->title('Translation Import Failed')
            ->body(new HtmlString(
                "The import for the translations of {$this->xlsformTemplate->title} {$this->locale->description} failed:<br/><br/>

                {$this->message}
")
            )
            ->danger()
            ->persistent()
            ->sendToDatabase($this->user, isEventDispatched: true)
            ->broadcast($this->user);

        LanguageImportIsComplete::dispatch($this->locale->id, $this->xlsformTemplate->id, $this->user->id);

        app(LocaleImportCounter::class)->finished($this->locale);
    }

    public function failed(?Throwable $exception = null): void
    {
        app(LocaleImportCounter::class)->finished($this->locale);

        $this->notifyJobFailure(
            "Translation import failed: {$this->xlsformTemplate->title} {$this->locale->description}",
            $exception,
            $this->user,
        );
    }
}
