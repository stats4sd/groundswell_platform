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
use Maatwebsite\Excel\Events\ImportFailed;

// A failed import notifies a user who may well have navigated away, so the notification has to
// survive in the database and carry a link to the page that explains the failure. These assert
// against the notifications table directly: Notification::assertNotified() only sees session
// notifications and would pass while sendToDatabase() was quietly broken.

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

function importRecordFor(string $modelType): Import
{
    return Import::create([
        'team_id' => test()->team->id,
        'user_id' => test()->user->id,
        'model_type' => $modelType,
    ]);
}

function firstNotificationAction(User $user): array
{
    return $user->notifications()->first()->data['actions'][0];
}

test('a failed location import leaves a database notification linking to its own import page', function () {
    $record = importRecordFor(Location::class);

    $import = new LocationImport([
        'import_id' => $record->id,
        'user_id' => $this->user->id,
        'owner_id' => $this->team->id,
        'level' => $this->level,
        'header_columns' => ['village_code' => 'village_code', 'village_name' => 'village_name'],
        'code_column' => 'village_code',
        'name_column' => 'village_name',
    ]);

    $handler = $import->registerEvents()[ImportFailed::class];
    $handler(new ImportFailed(new RuntimeException('bad location file')));

    expect($this->user->notifications()->count())->toBe(1);
    expect(firstNotificationAction($this->user)['url'])
        ->toEndWith("/app/{$this->team->id}/location-levels/imports/{$record->id}");
});

test('a failed farm import links to its own import page too', function () {
    $record = importRecordFor(FarmEntity::class);

    $import = new FarmEntityImport([
        'import_id' => $record->id,
        'user_id' => $this->user->id,
        'owner_id' => $this->team->id,
        'location_level_id' => $this->level->id,
        'header_columns' => ['farm_code' => 'farm_code', 'village_code' => 'village_code'],
        'farm_code_column' => 'farm_code',
        'location_code_column' => 'village_code',
        'farm_identifiers' => [],
        'farm_properties' => [],
    ]);

    $handler = $import->registerEvents()[ImportFailed::class];
    $handler(new ImportFailed(new RuntimeException('bad farm file')));

    expect(firstNotificationAction($this->user)['url'])
        ->toEndWith("/app/{$this->team->id}/location-levels/imports/{$record->id}");
});

test('a farm import that never started links to its import page as well', function () {
    $record = importRecordFor(FarmEntity::class);

    (new QueueFarmEntityImport(['user_id' => $this->user->id, 'owner_id' => $this->team->id], $record->id))
        ->failed(new RuntimeException('queue exploded'));

    expect(firstNotificationAction($this->user)['url'])
        ->toEndWith("/app/{$this->team->id}/location-levels/imports/{$record->id}");
});

// A QueryException's message is a whole SQL statement with its bindings; it used to be
// interpolated into the notification body in full.
test('the notification body is a summary, with the per-row detail left to the import page', function () {
    $record = importRecordFor(Location::class);

    $import = new LocationImport([
        'import_id' => $record->id,
        'user_id' => $this->user->id,
        'owner_id' => $this->team->id,
        'level' => $this->level,
        'header_columns' => ['village_code' => 'village_code', 'village_name' => 'village_name'],
        'code_column' => 'village_code',
        'name_column' => 'village_name',
    ]);

    $handler = $import->registerEvents()[ImportFailed::class];
    $handler(new ImportFailed(new RuntimeException(str_repeat('SQL ', 200))));

    expect(strlen($this->user->notifications()->first()->data['body']))->toBeLessThanOrEqual(210);
});
