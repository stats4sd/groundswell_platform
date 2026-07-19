<?php

use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Tests\Support\TranslationFixture;

test('the translation fixture builds a template with three default locales and a team-created locale', function () {
    $fixture = TranslationFixture::make();

    $templateLocaleIds = $fixture->template->locales->pluck('id');

    expect($templateLocaleIds)->toContain(
        $fixture->englishLocale->id,
        $fixture->frenchLocale->id,
        $fixture->spanishLocale->id,
        $fixture->abkhazianLocale->id,
    );

    $defaultLocales = $fixture->template->locales->filter(fn (Locale $locale) => $locale->is_default);

    expect($defaultLocales)->toHaveCount(3)
        ->and($fixture->team->languages)->toHaveCount(3)
        ->and($fixture->team->languages->pluck('iso_alpha2'))->not->toContain('es')
        ->and($fixture->abkhazianLocale->language_label)->toBe('Abkhazian (test)')
        ->and($fixture->englishLocale->language_label)->toBe('English (default)')
        ->and($fixture->template->surveyRows)->toHaveCount(2)
        ->and($fixture->template->choiceListEntries)->toHaveCount(2);
});
