<?php

namespace App\Services;

use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;

/**
 * Keeps locales.processing_count (the number of translation imports still running) correct while the web
 * request and several queue workers change it at the same time. Every change is a single SQL statement,
 * never a value computed from a model copy that may be out of date.
 */
class LocaleImportCounter
{
    public function started(Locale $locale, int $count = 1): void
    {
        if ($count < 1) {
            return;
        }

        Locale::whereKey($locale->getKey())->increment('processing_count', $count);
    }

    public function finished(Locale $locale): void
    {
        Locale::whereKey($locale->getKey())
            ->where('processing_count', '>', 0)
            ->decrement('processing_count');
    }
}
