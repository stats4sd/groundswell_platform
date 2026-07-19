<?php

use App\Imports\FarmEntityImport;
use Illuminate\Support\Facades\Validator;

// Covers the numeric/range validation applied to auto-detected GPS columns in
// FarmEntityImport::rules() - a selected identifier/property column whose header matches
// latitude/longitude/altitude/accuracy must pass the same bounds as the manual farm form
// (FarmEntityResource), so non-numeric or out-of-range cells surface as import errors
// instead of being silently (float)-cast to 0.0 and pushed to ODK Central.

function farmEntityImportData(array $overrides = []): array
{
    return array_merge([
        'header_columns' => [
            'farm_code' => 'farm_code',
            'location_code' => 'location_code',
            'latitude' => 'latitude',
            'longitude' => 'longitude',
            'altitude' => 'altitude',
            'accuracy' => 'accuracy',
            'family_name' => 'family_name',
        ],
        'farm_code_column' => 'farm_code',
        'location_code_column' => 'location_code',
        'location_level_id' => 1,
        'owner_id' => 1,
        'farm_identifiers' => ['family_name', 'latitude', 'longitude'],
        'farm_properties' => ['altitude', 'accuracy'],
    ], $overrides);
}

function gpsColumnRule(FarmEntityImport $import, string $column): array
{
    return $import->rules()[$column] ?? [];
}

test('auto-detected GPS columns get nullable numeric range rules', function () {
    $rules = (new FarmEntityImport(farmEntityImportData()))->rules();

    expect($rules['latitude'])->toBe(['nullable', 'numeric', 'between:-90,90']);
    expect($rules['longitude'])->toBe(['nullable', 'numeric', 'between:-180,180']);
    expect($rules['altitude'])->toBe(['nullable', 'numeric', 'between:-1240,60000']);
    expect($rules['accuracy'])->toBe(['nullable', 'numeric']);
});

test('a non-GPS identifier column gets no GPS rules', function () {
    $rules = (new FarmEntityImport(farmEntityImportData()))->rules();

    expect($rules)->not->toHaveKey('family_name');
});

test('a blank GPS cell passes validation', function () {
    $rule = gpsColumnRule(new FarmEntityImport(farmEntityImportData()), 'latitude');

    expect(Validator::make(['latitude' => null], ['latitude' => $rule])->passes())->toBeTrue();
});

test('a non-numeric GPS cell fails validation', function () {
    $rule = gpsColumnRule(new FarmEntityImport(farmEntityImportData()), 'latitude');

    expect(Validator::make(['latitude' => 'unknown'], ['latitude' => $rule])->passes())->toBeFalse();
});

test('an out-of-range latitude fails validation', function () {
    $rule = gpsColumnRule(new FarmEntityImport(farmEntityImportData()), 'latitude');

    expect(Validator::make(['latitude' => 100], ['latitude' => $rule])->passes())->toBeFalse();
    expect(Validator::make(['latitude' => 45.4215], ['latitude' => $rule])->passes())->toBeTrue();
});

test('an out-of-range longitude fails validation', function () {
    $rule = gpsColumnRule(new FarmEntityImport(farmEntityImportData()), 'longitude');

    expect(Validator::make(['longitude' => -181], ['longitude' => $rule])->passes())->toBeFalse();
    expect(Validator::make(['longitude' => -75.6972], ['longitude' => $rule])->passes())->toBeTrue();
});

test('GPS columns are only detected when ticked as identifiers or properties', function () {
    $data = farmEntityImportData([
        'farm_identifiers' => ['family_name'],
        'farm_properties' => [],
    ]);

    $rules = (new FarmEntityImport($data))->rules();

    expect($rules)->not->toHaveKey('latitude');
    expect($rules)->not->toHaveKey('longitude');
});
