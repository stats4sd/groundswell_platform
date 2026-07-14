<?php

use App\Services\OdkFarmEntityService;

// Covers the geometry <-> GPS-fields conversion (see docs/change-logs for the geometry
// format change): buildGeometryValue() (write side, app GPS fields -> Central's single
// `geometry` property) and parseGeometryValue() (read side, `geometry` -> GPS fields).
// Neither touches Central - pure local logic - so no Http::fake()/Team needed.

function odkFarmEntityGpsService(): OdkFarmEntityService
{
    return app(OdkFarmEntityService::class);
}

describe('OdkFarmEntityService::buildGeometryValue', function () {

    test('builds a space-separated latitude longitude altitude accuracy string', function () {
        $value = odkFarmEntityGpsService()->buildGeometryValue(45.4215, -75.6972, 70, 4.5);

        expect($value)->toBe('45.4215 -75.6972 70 4.5');
    });

    test('defaults a missing altitude and accuracy to 0', function () {
        $value = odkFarmEntityGpsService()->buildGeometryValue(45.4215, -75.6972, null, null);

        expect($value)->toBe('45.4215 -75.6972 0 0');
    });

    test('returns null when latitude is missing', function () {
        $value = odkFarmEntityGpsService()->buildGeometryValue(null, -75.6972, 70, 4.5);

        expect($value)->toBeNull();
    });

    test('returns null when longitude is missing', function () {
        $value = odkFarmEntityGpsService()->buildGeometryValue(45.4215, null, 70, 4.5);

        expect($value)->toBeNull();
    });

});

describe('OdkFarmEntityService::parseGeometryValue', function () {

    test('parses a full geometry string into the four GPS fields', function () {
        $gps = odkFarmEntityGpsService()->parseGeometryValue('45.4215 -75.6972 70.0 4.5');

        expect($gps)->toBe([
            'latitude' => '45.4215',
            'longitude' => '-75.6972',
            'altitude' => '70.0',
            'accuracy' => '4.5',
        ]);
    });

    test('leaves altitude and accuracy null when the string only has latitude/longitude', function () {
        $gps = odkFarmEntityGpsService()->parseGeometryValue('45.4215 -75.6972');

        expect($gps)->toBe([
            'latitude' => '45.4215',
            'longitude' => '-75.6972',
            'altitude' => null,
            'accuracy' => null,
        ]);
    });

    test('returns all null for a malformed value', function () {
        $gps = odkFarmEntityGpsService()->parseGeometryValue('not-a-geometry');

        expect($gps)->toBe([
            'latitude' => null,
            'longitude' => null,
            'altitude' => null,
            'accuracy' => null,
        ]);
    });

    test('round-trips through buildGeometryValue back to the same values', function () {
        $built = odkFarmEntityGpsService()->buildGeometryValue(45.4215, -75.6972, 70, 4.5);
        $parsed = odkFarmEntityGpsService()->parseGeometryValue($built);

        expect($parsed)->toBe([
            'latitude' => '45.4215',
            'longitude' => '-75.6972',
            'altitude' => '70',
            'accuracy' => '4.5',
        ]);
    });

});
