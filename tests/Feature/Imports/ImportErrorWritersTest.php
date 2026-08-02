<?php

use App\Imports\FarmEntityImport;
use App\Imports\LocationImport;
use App\Jobs\QueueFarmEntityImport;
use App\Models\Import;
use App\Models\SampleFrame\FarmEntity;
use App\Models\SampleFrame\Location;
use App\Models\SampleFrame\LocationLevel;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException as IlluminateValidationException;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\ImportFailed;
use Maatwebsite\Excel\Reader;
use Maatwebsite\Excel\Validators\Failure;
use Maatwebsite\Excel\Validators\ValidationException;

// Every writer of imports.errors must agree on the one shape Import::errorLines() reads, and
// must append rather than assign: ReadChunk::failed() raises ImportFailed once per failing
// chunk, so an assigning writer keeps only the last chunk's problems on a multi-chunk file.

beforeEach(function () {
    Http::fake();
    config()->set('broadcasting.default', 'null');

    $this->team = Team::factory()->create();
    $this->user = User::factory()->create();

    $this->level = LocationLevel::create([
        'owner_id' => $this->team->id,
        'name' => 'Village',
        'has_farms' => true,
    ]);
});

function validationExceptionWith(array $failures): ValidationException
{
    return new ValidationException(
        IlluminateValidationException::withMessages(['row' => 'invalid']),
        $failures,
    );
}

function farmImportRecord(): Import
{
    return Import::create([
        'team_id' => test()->team->id,
        'user_id' => test()->user->id,
        'model_type' => FarmEntity::class,
    ]);
}

function farmImportFor(Import $record): FarmEntityImport
{
    return new FarmEntityImport([
        'import_id' => $record->id,
        'user_id' => test()->user->id,
        'owner_id' => test()->team->id,
        'location_level_id' => test()->level->id,
        'header_columns' => ['farm_code' => 'farm_code', 'village_code' => 'village_code'],
        'farm_code_column' => 'farm_code',
        'location_code_column' => 'village_code',
        'farm_identifiers' => [],
        'farm_properties' => [],
    ]);
}

describe('FarmEntityImport writes the canonical error shape', function () {

    test('a validation failure is stored flat, without the old nested location wrapper', function () {
        $record = farmImportRecord();

        $handler = farmImportFor($record)->registerEvents()[ImportFailed::class];
        $handler(new ImportFailed(validationExceptionWith([
            new Failure(4, 'farm_code', ['The farm code cannot be empty.']),
        ])));

        expect($record->fresh()->errors->all())->toBe([
            ['row' => 4, 'attribute' => 'farm_code', 'errors' => ['The farm code cannot be empty.']],
        ]);
    });

    test('a non-validation failure is stored as one unattributed line', function () {
        $record = farmImportRecord();

        $handler = farmImportFor($record)->registerEvents()[ImportFailed::class];
        $handler(new ImportFailed(new RuntimeException('SQLSTATE[23000]: cannot be null')));

        expect($record->fresh()->errors->all())->toBe([
            ['row' => null, 'attribute' => null, 'errors' => ['SQLSTATE[23000]: cannot be null']],
        ]);
    });

    test('a second failing chunk accumulates instead of erasing the first', function () {
        $record = farmImportRecord();

        $handler = farmImportFor($record)->registerEvents()[ImportFailed::class];
        $handler(new ImportFailed(validationExceptionWith([new Failure(4, 'farm_code', ['Empty.'])])));
        $handler(new ImportFailed(validationExceptionWith([new Failure(1400, 'farm_code', ['Empty.'])])));

        expect($record->fresh()->error_lines->pluck('row')->all())->toBe([4, 1400]);
    });

    test('the failure notification body names the offending row instead of dumping the exception', function () {
        $record = farmImportRecord();

        $handler = farmImportFor($record)->registerEvents()[ImportFailed::class];
        $handler(new ImportFailed(validationExceptionWith([
            new Failure(4, 'farm_code', ['The farm code cannot be empty.']),
        ])));

        expect($this->user->notifications()->first()->data['body'])
            ->toContain('Row 4')
            ->toContain('The farm code cannot be empty.');
    });

    test('a completed import records success and a finish time', function () {
        $record = farmImportRecord();

        $handler = farmImportFor($record)->registerEvents()[AfterImport::class];
        $handler(new AfterImport(app(Reader::class), farmImportFor($record)));

        expect($record->fresh()->success)->toBeTrue();
        expect($record->fresh()->finished_at)->not->toBeNull();
        expect($record->fresh()->status)->toBe('complete');
    });

});

describe('LocationImport writes the canonical error shape', function () {

    function locationImportRecord(): Import
    {
        return Import::create([
            'team_id' => test()->team->id,
            'user_id' => test()->user->id,
            'model_type' => Location::class,
        ]);
    }

    function locationImportFor(Import $record, array $overrides = []): LocationImport
    {
        return new LocationImport([
            'import_id' => $record->id,
            'user_id' => test()->user->id,
            'owner_id' => test()->team->id,
            'level' => test()->level,
            'header_columns' => ['village_code' => 'village_code', 'village_name' => 'village_name'],
            'code_column' => 'village_code',
            'name_column' => 'village_name',
            ...$overrides,
        ]);
    }

    test('a validation failure is stored against its row and column', function () {
        $record = locationImportRecord();

        $handler = locationImportFor($record)->registerEvents()[ImportFailed::class];
        $handler(new ImportFailed(validationExceptionWith([
            new Failure(3, 'village_code', ["The 'village_code' column is empty."]),
        ])));

        expect($record->fresh()->errors->all())->toBe([
            ['row' => 3, 'attribute' => 'village_code', 'errors' => ["The 'village_code' column is empty."]],
        ]);
    });

    test('a second failing chunk accumulates instead of erasing the first', function () {
        $record = locationImportRecord();

        $handler = locationImportFor($record)->registerEvents()[ImportFailed::class];
        $handler(new ImportFailed(validationExceptionWith([new Failure(3, 'village_code', ['Empty.'])])));
        $handler(new ImportFailed(validationExceptionWith([new Failure(1200, 'village_code', ['Empty.'])])));

        expect($record->fresh()->error_lines->pluck('row')->all())->toBe([3, 1200]);
    });

    test('the dependent farm import keeps its own explanation alongside a later one', function () {
        $record = locationImportRecord();
        $dependent = farmImportRecord();

        $handler = locationImportFor($record, ['dependent_import_id' => $dependent->id])
            ->registerEvents()[ImportFailed::class];

        $handler(new ImportFailed(new RuntimeException('bad location file')));
        $handler(new ImportFailed(new RuntimeException('bad location file')));

        expect($dependent->fresh()->error_count)->toBe(2);
        expect($dependent->fresh()->status)->toBe('failed');
    });

    test('a completed import records success and notifies the database, not only the browser', function () {
        $record = locationImportRecord();

        $import = locationImportFor($record);
        $handler = $import->registerEvents()[AfterImport::class];
        $handler(new AfterImport(app(Reader::class), $import));

        expect($record->fresh()->success)->toBeTrue();
        expect($record->fresh()->status)->toBe('complete');
        expect($this->user->notifications()->count())->toBe(1);
    });

});

describe('QueueFarmEntityImport', function () {

    test('a job that never started is recorded and reported to the user who asked for it', function () {
        $record = farmImportRecord();

        (new QueueFarmEntityImport(['user_id' => $this->user->id], $record->id))
            ->failed(new RuntimeException('queue exploded'));

        expect($record->fresh()->errors->all())->toBe([
            ['row' => null, 'attribute' => null, 'errors' => ['queue exploded']],
        ]);

        expect($this->user->notifications()->first()->data['title'])->toBe('Import of Farm Data Failed');
    });

    test('it notifies nobody when the job data carries no user', function () {
        $record = farmImportRecord();

        (new QueueFarmEntityImport([], $record->id))->failed(new RuntimeException('queue exploded'));

        expect($record->fresh()->error_count)->toBe(1);
        expect($this->user->notifications()->count())->toBe(0);
    });

});
