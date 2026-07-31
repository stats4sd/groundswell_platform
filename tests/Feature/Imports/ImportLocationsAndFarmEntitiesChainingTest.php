<?php

use App\Filament\App\Clusters\LocationLevels\Resources\FarmEntityResource\Pages\ImportLocationsAndFarmEntities;
use App\Imports\FarmEntityImport;
use App\Imports\LocationImport;
use App\Jobs\QueueFarmEntityImport;
use App\Models\Import;
use App\Models\SampleFrame\FarmEntity;
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

        expect($this->locationImportRecord->fresh()->errors->first())->toBe('bad location file');
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

        expect($this->locationImportRecord->fresh()->errors->first())->toBe('bad location file');
    });

});

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
