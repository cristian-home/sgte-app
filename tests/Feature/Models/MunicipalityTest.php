<?php

use App\Models\Department;
use App\Models\Driver;
use App\Models\Municipality;
use App\Models\ThirdParty;
use App\Models\Vehicle;

test('municipality can be created via factory', function (): void {
    $municipality = Municipality::factory()->create();

    expect($municipality)->toBeInstanceOf(Municipality::class);
    expect($municipality->exists)->toBeTrue();
});

test('department relationship returns Department instance', function (): void {
    $municipality = Municipality::factory()->create();

    expect($municipality->department)->toBeInstanceOf(Department::class);
});

test('vehicles relationship returns Vehicle instances', function (): void {
    $municipality = Municipality::factory()->create();
    Vehicle::factory()->count(2)->create(['municipality_id' => $municipality->id]);

    expect($municipality->vehicles)->toHaveCount(2);
    expect($municipality->vehicles->first())->toBeInstanceOf(Vehicle::class);
});

test('drivers relationship returns Driver instances', function (): void {
    $municipality = Municipality::factory()->create();
    Driver::factory()->count(2)->create(['municipality_id' => $municipality->id]);

    expect($municipality->drivers)->toHaveCount(2);
    expect($municipality->drivers->first())->toBeInstanceOf(Driver::class);
});

test('thirdParties relationship returns ThirdParty instances', function (): void {
    $municipality = Municipality::factory()->create();
    ThirdParty::factory()->count(2)->create(['municipality_id' => $municipality->id]);

    expect($municipality->thirdParties)->toHaveCount(2);
    expect($municipality->thirdParties->first())->toBeInstanceOf(ThirdParty::class);
});
