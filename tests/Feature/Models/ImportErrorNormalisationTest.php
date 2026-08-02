<?php

use App\Models\Import;
use App\Models\SampleFrame\Location;
use App\Models\Team;

// imports.errors has held four different payload shapes over the life of the table and old rows
// are never rewritten, so every surface reads through Import::errorLines(). These pin that
// accessor against all four, because a new one is only ever a writer edit away.

beforeEach(function () {
    $this->team = Team::factory()->create();
});

function importWithErrors(mixed $errors, array $attributes = []): Import
{
    return Import::create([
        'team_id' => test()->team->id,
        'model_type' => Location::class,
        'errors' => $errors,
        ...$attributes,
    ]);
}

describe('error_lines normalisation', function () {

    test('null errors produce no lines', function () {
        expect(importWithErrors(null)->error_lines->all())->toBe([]);
    });

    test('an empty error list produces no lines', function () {
        expect(importWithErrors([])->error_lines->all())->toBe([]);
    });

    test('the canonical flat shape passes through with errors renamed to messages', function () {
        $import = importWithErrors([
            ['row' => 14, 'attribute' => 'loc1_code', 'errors' => ['The value is required.']],
        ]);

        expect($import->error_lines->all())->toBe([
            ['row' => 14, 'attribute' => 'loc1_code', 'messages' => ['The value is required.']],
        ]);
    });

    test("FarmEntityImport's old nested shape is flattened", function () {
        $import = importWithErrors([
            ['location' => ['row' => 7, 'column' => 'farm_code'], 'errors' => ['The farm code cannot be empty.']],
        ]);

        expect($import->error_lines->all())->toBe([
            ['row' => 7, 'attribute' => 'farm_code', 'messages' => ['The farm code cannot be empty.']],
        ]);
    });

    test('a bare exception message string becomes a single unattributed line', function () {
        $import = importWithErrors('SQLSTATE[23000]: Column "code" cannot be null');

        expect($import->error_lines->all())->toBe([
            ['row' => null, 'attribute' => null, 'messages' => ['SQLSTATE[23000]: Column "code" cannot be null']],
        ]);
    });

    test('a line whose errors is a string rather than an array is still read', function () {
        $import = importWithErrors([
            ['row' => 3, 'attribute' => 'name', 'errors' => 'A single message.'],
        ]);

        expect($import->error_lines->all())->toBe([
            ['row' => 3, 'attribute' => 'name', 'messages' => ['A single message.']],
        ]);
    });

    test('a row number stored as a string is cast to an int', function () {
        $import = importWithErrors([
            ['row' => '14', 'attribute' => 'code', 'errors' => ['Required.']],
        ]);

        expect($import->error_lines->first()['row'])->toBe(14);
    });

    test('lines carrying no message at all are dropped', function () {
        $import = importWithErrors([
            ['row' => 1, 'attribute' => 'code', 'errors' => []],
            ['row' => 2, 'attribute' => 'code', 'errors' => ['Required.']],
        ]);

        expect($import->error_lines->all())->toBe([
            ['row' => 2, 'attribute' => 'code', 'messages' => ['Required.']],
        ]);
    });

});

describe('error_count', function () {

    test('it totals every message across every line', function () {
        $import = importWithErrors([
            ['row' => 1, 'attribute' => 'code', 'errors' => ['Required.', 'Too long.']],
            ['row' => 2, 'attribute' => 'name', 'errors' => ['Required.']],
        ]);

        expect($import->error_count)->toBe(3);
    });

    test('it is zero when there are no errors', function () {
        expect(importWithErrors(null)->error_count)->toBe(0);
    });

});

describe('status derivation', function () {

    test('an import holding errors is failed, even if it also recorded success', function () {
        $import = importWithErrors(
            [['row' => 1, 'attribute' => 'code', 'errors' => ['Required.']]],
            ['success' => true],
        );

        expect($import->status)->toBe('failed');
    });

    test('a successful error-free import is complete', function () {
        expect(importWithErrors(null, ['success' => true])->status)->toBe('complete');
    });

    test('a fresh import with neither errors nor success is pending', function () {
        expect(importWithErrors(null)->status)->toBe('pending');
    });

    test('an import still pending after an hour is stale, because an OOM-killed worker never reports', function () {
        $import = importWithErrors(null);
        $import->update(['created_at' => now()->subHours(2)]);

        expect($import->fresh()->status)->toBe('stale');
    });

});

describe('appendErrorLines', function () {

    test('a second failing chunk adds to the first chunk rather than replacing it', function () {
        $import = importWithErrors(null);

        $import->appendErrorLines([['row' => 5, 'attribute' => 'code', 'errors' => ['Required.']]]);
        $import->appendErrorLines([['row' => 1400, 'attribute' => 'code', 'errors' => ['Required.']]]);

        expect($import->fresh()->error_lines->pluck('row')->all())->toBe([5, 1400]);
    });

    test('it refreshes the model it was called on, not only the database row', function () {
        $import = importWithErrors(null);

        $import->appendErrorLines([['row' => 5, 'attribute' => 'code', 'errors' => ['Required.']]]);

        expect($import->error_count)->toBe(1);
    });

    test('appending to a legacy payload rewrites it into the canonical shape', function () {
        $import = importWithErrors([
            ['location' => ['row' => 7, 'column' => 'farm_code'], 'errors' => ['Empty.']],
        ]);

        $import->appendErrorLines([['row' => 8, 'attribute' => 'farm_code', 'errors' => ['Empty.']]]);

        expect($import->fresh()->errors->all())->toBe([
            ['row' => 7, 'attribute' => 'farm_code', 'errors' => ['Empty.']],
            ['row' => 8, 'attribute' => 'farm_code', 'errors' => ['Empty.']],
        ]);
    });

    test('a deleted import record is a no-op rather than a crash', function () {
        $import = importWithErrors(null);
        $import->delete();

        $import->appendErrorLines([['row' => 1, 'attribute' => 'code', 'errors' => ['Required.']]]);

        expect(Import::count())->toBe(0);
    });

});
