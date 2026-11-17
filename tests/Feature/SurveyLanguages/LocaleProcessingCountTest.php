<?php

use App\Jobs\NotifyUserThatLanguageImportIsComplete;
use App\Jobs\NotifyUserThatLanguageImportIsFailed;
use App\Services\LocaleImportCounter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Support\TranslationFixture;

function storedProcessingCount($locale): int
{
    return (int) DB::table('locales')->where('id', $locale->id)->value('processing_count');
}

test('the counter changes the stored value, not a stale copy', function () {
    $fixture = TranslationFixture::make();
    $staleCopy = $fixture->abkhazianLocale->fresh();
    $counter = app(LocaleImportCounter::class);

    $counter->started($fixture->abkhazianLocale, 3);
    $counter->finished($staleCopy);

    expect(storedProcessingCount($fixture->abkhazianLocale))->toBe(2);
});

test('the counter never goes below zero', function () {
    $fixture = TranslationFixture::make();
    $counter = app(LocaleImportCounter::class);

    $counter->finished($fixture->abkhazianLocale);
    $counter->finished($fixture->abkhazianLocale);

    expect(storedProcessingCount($fixture->abkhazianLocale))->toBe(0);
});

test('starting zero imports changes nothing', function () {
    $fixture = TranslationFixture::make();

    app(LocaleImportCounter::class)->started($fixture->abkhazianLocale, 0);

    expect(storedProcessingCount($fixture->abkhazianLocale))->toBe(0);
});

test('the failed-import job releases one count', function () {
    Event::fake();
    $fixture = TranslationFixture::make();
    $user = createAppUser($fixture->team);
    app(LocaleImportCounter::class)->started($fixture->abkhazianLocale, 2);

    (new NotifyUserThatLanguageImportIsFailed($fixture->abkhazianLocale, $fixture->template, $user, 'boom'))->handle();

    expect(storedProcessingCount($fixture->abkhazianLocale))->toBe(1);
});

test('the completion job releases one count when it finishes', function () {
    Event::fake();
    $fixture = TranslationFixture::make();
    $user = createAppUser($fixture->team);
    app(LocaleImportCounter::class)->started($fixture->abkhazianLocale, 2);

    (new NotifyUserThatLanguageImportIsComplete($fixture->abkhazianLocale, $fixture->template, $user))->handle();

    expect(storedProcessingCount($fixture->abkhazianLocale))->toBe(1);
});

test('a completion job that fails permanently releases one count, and a failed-job handler releases it once', function () {
    $fixture = TranslationFixture::make();
    $user = createAppUser($fixture->team);
    app(LocaleImportCounter::class)->started($fixture->abkhazianLocale, 2);

    (new NotifyUserThatLanguageImportIsComplete($fixture->abkhazianLocale, $fixture->template, $user))
        ->failed(new Exception('boom'));

    expect(storedProcessingCount($fixture->abkhazianLocale))->toBe(1);

    (new NotifyUserThatLanguageImportIsFailed($fixture->abkhazianLocale, $fixture->template, $user, 'boom'))
        ->failed(new Exception('boom'));

    expect(storedProcessingCount($fixture->abkhazianLocale))->toBe(0);
});

test('a completion job that throws before the end keeps the count until failed() releases it', function () {
    Event::fake();
    $fixture = TranslationFixture::make();
    $user = createAppUser($fixture->team);
    app(LocaleImportCounter::class)->started($fixture->abkhazianLocale, 1);

    $fixture->abkhazianLocale->creator_id = null;
    $fixture->abkhazianLocale->saveQuietly();

    $job = new NotifyUserThatLanguageImportIsComplete($fixture->abkhazianLocale->fresh(), $fixture->template, $user);

    $thrown = null;

    try {
        $job->handle();
    } catch (Throwable $exception) {
        $thrown = $exception;
    }

    expect($thrown)->not->toBeNull()
        ->and(storedProcessingCount($fixture->abkhazianLocale))->toBe(1);

    $job->failed(new Exception('boom'));

    expect(storedProcessingCount($fixture->abkhazianLocale))->toBe(0);
});
