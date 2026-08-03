<?php

use App\Filament\App\Clusters\LocationLevels\Resources\FarmEntityResource\Pages\ImportLocationsAndFarmEntities;
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
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Events\ImportFailed;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Jobs\QueueImport;
use Maatwebsite\Excel\Validators\ValidationException;

use function Pest\Livewire\livewire;

// Regression coverage for finding M13 (docs/code-reviews/2026-07-19-map-location-to-entity-list-attribute.md):
// the combined wizard used to call Excel::import() twice, producing two independent job
// chains on the same queue. With more than one worker the farm chunks could run before the
// location chunks had committed, and FarmEntityImport::rules() validates its location column
// with Rule::exists('locations', 'code') - so the whole farm import failed. The farm import
// is now appended to the tail of the location import's own chain.

function combinedImportSpreadsheet(): string
{
    $export = new class implements FromArray
    {
        public function array(): array
        {
            return [
                ['village_code', 'village_name', 'farm_code', 'family_name'],
                ['V1', 'Alpha', 'F1', 'Smith'],
                ['V2', 'Beta', 'F2', 'Jones'],
            ];
        }
    };

    Excel::store($export, 'combined-import.xlsx', null, ExcelWriter::XLSX);

    return 'combined-import.xlsx';
}

function submitCombinedImportWizard(Team $team, User $user, LocationLevel $level): void
{
    $upload = combinedImportSpreadsheet();

    Queue::fake();

    livewire(ImportLocationsAndFarmEntities::class)
        ->fillForm([
            'upload' => ['fake-upload-uuid' => $upload],
            'header_columns' => [
                'village_code' => 'village_code',
                'village_name' => 'village_name',
                'farm_code' => 'farm_code',
                'family_name' => 'family_name',
            ],
            'code_column' => 'village_code',
            'name_column' => 'village_name',
            'level' => $level,
            'user_id' => $user->id,
            'owner_id' => $team->id,
            'location_level_id' => $level->id,
            'location_code_column' => 'village_code',
            'farm_code_column' => 'farm_code',
            'farm_identifiers' => ['family_name'],
            'farm_properties' => [],
        ])
        ->call('save')
        ->assertHasNoFormErrors();
}

describe('ImportLocationsAndFarmEntities chains the farm import after the location import', function () {

    beforeEach(function () {
        Http::fake();
        Storage::fake(config('filesystems.default'));

        $this->team = Team::factory()->create();
        $this->user = createAppUser($this->team);
        $this->actingAs($this->user);

        $this->level = LocationLevel::create([
            'owner_id' => $this->team->id,
            'name' => 'Village',
            'has_farms' => true,
        ]);

        withAppTenant($this->team);
    });

    test('one queued chain is pushed, with the farm import as its tail', function () {
        submitCombinedImportWizard($this->team, $this->user, $this->level);

        Queue::assertPushed(QueueImport::class, 1);

        Queue::assertPushed(QueueImport::class, function (QueueImport $job) {
            $tail = unserialize(end($job->chained));

            return $tail instanceof QueueFarmEntityImport;
        });
    });

    test('the chained farm job carries the farm Import id and no LocationLevel model', function () {
        submitCombinedImportWizard($this->team, $this->user, $this->level);

        $farmImport = Import::where('model_type', FarmEntity::class)->sole();

        Queue::assertPushed(QueueImport::class, function (QueueImport $job) use ($farmImport) {
            /** @var QueueFarmEntityImport $tail */
            $tail = unserialize(end($job->chained));

            return $tail->importId === $farmImport->id
                && $tail->data['import_id'] === $farmImport->id
                && ! array_key_exists('level', $tail->data);
        });
    });

});

describe('QueueFarmEntityImport', function () {

    beforeEach(function () {
        Http::fake();
        Storage::fake(config('filesystems.default'));

        $this->team = Team::factory()->create();
    });

    test('handle() imports the farm Import record media with a FarmEntityImport', function () {
        $upload = combinedImportSpreadsheet();

        $import = Import::create(['team_id' => $this->team->id, 'model_type' => FarmEntity::class]);
        $import->addMedia(Storage::path($upload))->preservingOriginal()->toMediaCollection();

        Excel::fake();

        (new QueueFarmEntityImport(['owner_id' => $this->team->id], $import->id))->handle();

        Excel::assertImported($import->getFirstMediaPath(), fn ($import) => $import instanceof FarmEntityImport);
    });

    test('failed() records the exception on the farm Import record', function () {
        $import = Import::create(['team_id' => $this->team->id, 'model_type' => FarmEntity::class]);

        (new QueueFarmEntityImport([], $import->id))->failed(new RuntimeException('queue exploded'));

        expect($import->fresh()->errors->first()['errors'])->toBe(['queue exploded']);
    });

});

describe('LocationImport failure propagation', function () {

    beforeEach(function () {
        Http::fake();
        config()->set('broadcasting.default', 'null');

        $this->team = Team::factory()->create();
        $this->user = User::factory()->create();

        $this->locationImportRecord = Import::create(['team_id' => $this->team->id, 'model_type' => 'Location']);
    });

    test('a failed location import explains the skip on the dependent farm Import record', function () {
        $farmImportRecord = Import::create(['team_id' => $this->team->id, 'model_type' => FarmEntity::class]);

        $import = new LocationImport(locationImportData($this->team, $this->user, [
            'import_id' => $this->locationImportRecord->id,
            'dependent_import_id' => $farmImportRecord->id,
        ]));

        $handler = $import->registerEvents()[ImportFailed::class];
        $handler(new ImportFailed(new RuntimeException('bad location file')));

        expect($this->locationImportRecord->fresh()->errors->first())->toBe([
            'row' => null,
            'attribute' => null,
            'errors' => ['bad location file'],
        ]);

        expect($farmImportRecord->fresh()->errors->first()['errors'][0])
            ->toContain('skipped because the location import it depends on failed')
            ->toContain('bad location file');
    });

    test('a standalone location import failure works without a dependent import', function () {
        $import = new LocationImport(locationImportData($this->team, $this->user, [
            'import_id' => $this->locationImportRecord->id,
        ]));

        $handler = $import->registerEvents()[ImportFailed::class];
        $handler(new ImportFailed(new RuntimeException('bad location file')));

        expect($this->locationImportRecord->fresh()->errors->first()['errors'])->toBe(['bad location file']);
    });

    test('the user is notified, so a failed location import is not silent', function () {
        $import = new LocationImport(locationImportData($this->team, $this->user, [
            'import_id' => $this->locationImportRecord->id,
        ]));

        $handler = $import->registerEvents()[ImportFailed::class];
        $handler(new ImportFailed(new RuntimeException('bad location file')));

        expect($this->user->notifications()->count())->toBe(1);
        expect($this->user->notifications()->first()->data['title'])->toBe('Import of Location Data Failed');
        expect($this->user->notifications()->first()->data['body'])->toContain('bad location file');
    });

});

describe('LocationImport row validation', function () {

    beforeEach(function () {
        Http::fake();
        Storage::fake(config('filesystems.default'));
        config()->set('broadcasting.default', 'null');

        $this->team = Team::factory()->create();
        $this->user = User::factory()->create();

        $this->level = LocationLevel::create([
            'owner_id' => $this->team->id,
            'name' => 'Village',
            'has_farms' => true,
        ]);

        $this->locationImportRecord = Import::create(['team_id' => $this->team->id, 'model_type' => Location::class]);
    });

    // locations.code and locations.name are NOT NULL, so before these rules existed a single
    // blank cell aborted the whole chunk transaction with a raw "Column 'code' cannot be null"
    // that named neither the row nor the column.
    test('a blank code cell is reported against its row and column, and no locations are created', function () {
        $failure = importSpreadsheetWithBlankVillageCode($this);

        expect(Location::count())->toBe(0);
        expect($failure['row'])->toBe(3);
        expect($failure['attribute'])->toBe('village_code');
        expect($failure['errors'][0])
            ->toContain("The 'village_code' column is empty")
            ->toContain('Village unique code');
    });

    test('the dependent farm import is told which row broke the location import', function () {
        $farmImportRecord = Import::create(['team_id' => $this->team->id, 'model_type' => FarmEntity::class]);

        importSpreadsheetWithBlankVillageCode($this, ['dependent_import_id' => $farmImportRecord->id]);

        expect($farmImportRecord->fresh()->errors->first()['errors'][0])
            ->toContain('skipped because the location import it depends on failed')
            ->toContain('Row 3');
    });

    test('rules() requires every column the user mapped, at the level and at every parent level', function () {
        $parent = LocationLevel::create(['owner_id' => $this->team->id, 'name' => 'District']);
        $this->level->update(['parent_id' => $parent->id]);

        $import = new LocationImport([
            'header_columns' => [
                'district_code' => 'district_code',
                'district_name' => 'district_name',
                'village_code' => 'village_code',
                'village_name' => 'village_name',
            ],
            'code_column' => 'village_code',
            'name_column' => 'village_name',
            "parent_{$parent->id}_code_column" => 'district_code',
            "parent_{$parent->id}_name_column" => 'district_name',
            'level' => $this->level,
            'owner_id' => $this->team->id,
            'user_id' => $this->user->id,
        ]);

        expect($import->rules())->toBe([
            'district_code' => ['required'],
            'district_name' => ['required'],
            'village_code' => ['required'],
            'village_name' => ['required'],
        ]);

        expect($import->customValidationMessages()['district_code.required'])
            ->toContain('District unique code');
    });

});

function importSpreadsheetWithBlankVillageCode($test, array $overrides = []): array
{
    $export = new class implements FromArray
    {
        public function array(): array
        {
            return [
                ['village_code', 'village_name'],
                ['V1', 'Alpha'],
                ['', 'Beta'],
            ];
        }
    };

    Excel::store($export, 'blank-code.xlsx', null, ExcelWriter::XLSX);

    $test->locationImportRecord->addMedia(Storage::path('blank-code.xlsx'))->toMediaCollection();

    $data = locationImportData($test->team, $test->user, array_merge([
        'import_id' => $test->locationImportRecord->id,
        'level' => $test->level,
    ], $overrides));

    expect(fn () => Excel::import(new LocationImport($data), $test->locationImportRecord->getFirstMediaPath()))
        ->toThrow(ValidationException::class);

    return $test->locationImportRecord->fresh()->errors->first();
}

function locationImportData(Team $team, User $user, array $overrides = []): array
{
    return array_merge([
        'header_columns' => [
            'village_code' => 'village_code',
            'village_name' => 'village_name',
        ],
        'code_column' => 'village_code',
        'name_column' => 'village_name',
        'owner_id' => $team->id,
        'user_id' => $user->id,
    ], $overrides);
}
