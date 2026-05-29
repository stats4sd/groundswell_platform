<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncAndPurgeTranslations extends Command
{
    protected $signature = 'translation:sync_and_purge';

    protected $description = 'Sync translations and remove unused keys from Translation.io (wraps vendor command to include resources/text/*.md stubs)';

    public function handle(): int
    {
        try {
            $this->call(GenerateTranslationStubs::class);
            $this->call(\Tio\Laravel\Console\Commands\SyncAndPurge::class);
        } finally {
            foreach (glob(resource_path('text/*.php')) as $stub) {
                unlink($stub);
            }
            $this->info('Translation stubs cleaned up.');
        }

        return self::SUCCESS;
    }
}
