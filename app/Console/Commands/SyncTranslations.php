<?php

namespace App\Console\Commands;

use Tio\Laravel\Console\Commands\Sync;
use Illuminate\Console\Command;

class SyncTranslations extends Command
{
    protected $signature = 'translation:sync';

    protected $description = 'Send new translatable keys/strings and get new translations from Translation.io (wraps vendor command to include resources/text/*.md stubs)';

    public function handle(): int
    {
        try {
            $this->call(GenerateTranslationStubs::class);
            $this->call(Sync::class);
        } finally {
            foreach (glob(resource_path('text/*.php')) as $stub) {
                unlink($stub);
            }
            $this->info('Translation stubs cleaned up.');
        }

        return self::SUCCESS;
    }
}
