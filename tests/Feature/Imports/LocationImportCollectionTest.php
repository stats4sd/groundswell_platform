<?php

use App\Imports\LocationImport;
use App\Models\SampleFrame\Location;
use App\Models\SampleFrame\LocationLevel;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake();
    config()->set('broadcasting.default', 'null');

    $this->team = Team::factory()->create();
    $this->user = User::factory()->create();

    $this->district = LocationLevel::create([
        'owner_id' => $this->team->id,
        'name' => 'District',
    ]);

    $this->village = LocationLevel::create([
        'owner_id' => $this->team->id,
        'name' => 'Village',
        'parent_id' => $this->district->id,
        'has_farms' => true,
    ]);
});

function villageImport($test): LocationImport
{
    return new LocationImport([
        'header_columns' => [
            'district_code' => 'district_code',
            'district_name' => 'district_name',
            'village_code' => 'village_code',
            'village_name' => 'village_name',
        ],
        'code_column' => 'village_code',
        'name_column' => 'village_name',
        "parent_{$test->district->id}_code_column" => 'district_code',
        "parent_{$test->district->id}_name_column" => 'district_name',
        'level' => $test->village,
        'owner_id' => $test->team->id,
        'user_id' => $test->user->id,
    ]);
}

function villageRows(array $rows): Collection
{
    return collect($rows)->map(fn (array $row) => array_combine(
        ['district_code', 'district_name', 'village_code', 'village_name'],
        $row,
    ));
}

// The import flags the owner through its own Team instance, so clearing the flag has to go
// straight to the database rather than through the stale model the test is holding.
function clearOwnerFlag(Team $team): void
{
    Team::whereKey($team->id)->update(['has_updated_locations' => false]);
}

function countQueries(callable $callback): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    $callback();

    $queries = count(DB::getQueryLog());

    DB::disableQueryLog();

    return $queries;
}

test('the hierarchy is built and each location is parented to the level above it', function () {
    villageImport($this)->collection(villageRows([
        ['D1', 'North', 'V1', 'Alpha'],
        ['D1', 'North', 'V2', 'Beta'],
        ['D2', 'South', 'V3', 'Gamma'],
    ]));

    expect(Location::where('location_level_id', $this->district->id)->pluck('code')->all())
        ->toBe(['D1', 'D2']);

    $alpha = Location::where('code', 'V1')->sole();

    expect($alpha->name)->toBe('Alpha')
        ->and($alpha->parent->code)->toBe('D1')
        ->and($alpha->owner_id)->toBe($this->team->id);

    expect(Location::where('code', 'V3')->sole()->parent->code)->toBe('D2');
});

test('a parent repeated across rows is created once', function () {
    villageImport($this)->collection(villageRows([
        ['D1', 'North', 'V1', 'Alpha'],
        ['D1', 'North', 'V2', 'Beta'],
        ['D1', 'North', 'V3', 'Gamma'],
    ]));

    expect(Location::where('code', 'D1')->count())->toBe(1)
        ->and(Location::count())->toBe(4);
});

test('re-importing the same rows reuses every existing location instead of duplicating it', function () {
    $rows = villageRows([
        ['D1', 'North', 'V1', 'Alpha'],
        ['D1', 'North', 'V2', 'Beta'],
    ]);

    villageImport($this)->collection($rows);
    villageImport($this)->collection($rows);

    expect(Location::count())->toBe(3);
});

// MySQL's collation matches locations.code case-insensitively and ignores trailing whitespace,
// so an in-memory index that did not normalise both sides would insert duplicates here.
test('codes differing only by case or surrounding whitespace match an existing location', function () {
    villageImport($this)->collection(villageRows([
        ['D1', 'North', 'V1', 'Alpha'],
    ]));

    villageImport($this)->collection(villageRows([
        [' d1 ', 'North', 'v1  ', 'Alpha'],
    ]));

    expect(Location::count())->toBe(2);
});

test('a numeric code cell matches the string already stored in the column', function () {
    Location::create([
        'owner_id' => $this->team->id,
        'location_level_id' => $this->district->id,
        'code' => '7',
        'name' => 'Seven',
    ]);

    villageImport($this)->collection(villageRows([
        [7, 'Seven', 'V1', 'Alpha'],
    ]));

    expect(Location::where('location_level_id', $this->district->id)->count())->toBe(1)
        ->and(Location::where('code', 'V1')->sole()->parent->code)->toBe('7');
});

test('an existing location is reused rather than renamed', function () {
    villageImport($this)->collection(villageRows([
        ['D1', 'North', 'V1', 'Alpha'],
    ]));

    villageImport($this)->collection(villageRows([
        ['D1', 'North', 'V1', 'Renamed'],
    ]));

    expect(Location::where('code', 'V1')->sole()->name)->toBe('Alpha');
});

test('the owner is flagged once for the whole chunk rather than once per saved location', function () {
    clearOwnerFlag($this->team);

    villageImport($this)->collection(villageRows([
        ['D1', 'North', 'V1', 'Alpha'],
        ['D1', 'North', 'V2', 'Beta'],
    ]));

    expect($this->team->fresh()->has_updated_locations)->toBeTrue();
});

test('a chunk that creates nothing leaves the owner flag alone', function () {
    $rows = villageRows([['D1', 'North', 'V1', 'Alpha']]);

    villageImport($this)->collection($rows);

    clearOwnerFlag($this->team);

    villageImport($this)->collection($rows);

    expect($this->team->fresh()->has_updated_locations)->toBeFalse();
});

test('ancestors of a created location still have their timestamps bumped', function () {
    villageImport($this)->collection(villageRows([
        ['D1', 'North', 'V1', 'Alpha'],
    ]));

    $district = Location::where('code', 'D1')->sole();
    $district->update(['updated_at' => now()->subDay()]);

    villageImport($this)->collection(villageRows([
        ['D1', 'North', 'V2', 'Beta'],
    ]));

    expect($district->fresh()->updated_at->isToday())->toBeTrue();
});

// The point of the in-memory index: lookups no longer cost a query, so a chunk with nothing to
// create is a single SELECT no matter how many rows it holds.
test('a chunk that creates nothing costs one query regardless of its size', function () {
    $rows = villageRows(array_map(
        fn (int $index) => ['D1', 'North', "V{$index}", "Village {$index}"],
        range(1, 25),
    ));

    villageImport($this)->collection($rows);

    expect(countQueries(fn () => villageImport($this)->collection($rows)))->toBe(1);
});

// The fixed cost is one SELECT to load the owner's locations, one UPDATE to bump the ancestors
// and three to flag the owner; everything else is one INSERT per location actually created.
test('query count grows by one insert per created location, not by six per row', function () {
    $rowsFor = fn (int $count) => villageRows(array_map(
        fn (int $index) => ['D1', 'North', "V{$index}", "Village {$index}"],
        range(1, $count),
    ));

    clearOwnerFlag($this->team);

    expect(countQueries(fn () => villageImport($this)->collection($rowsFor(5))))->toBe(11);

    Location::query()->delete();
    clearOwnerFlag($this->team);

    expect(countQueries(fn () => villageImport($this)->collection($rowsFor(25))))->toBe(31);
});
