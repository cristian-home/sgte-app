<?php

use App\Models\Department;
use App\Models\Municipality;
use Database\Seeders\DepartmentAndMunicipalitySeeder;

test('seeder populates departments table with expected count', function (): void {
    $this->seed(DepartmentAndMunicipalitySeeder::class);

    expect(Department::count())->toBe(33);
});

test('seeder creates municipalities with correct department relationships', function (): void {
    $this->seed(DepartmentAndMunicipalitySeeder::class);

    $municipality = Municipality::where('code', '5001')->first();

    expect($municipality)->not->toBeNull();
    expect($municipality->name)->toBe('MEDELLÍN');
    expect($municipality->department)->not->toBeNull();
    expect($municipality->department->name)->toBe('ANTIOQUIA');
});

test('municipality codes are unique', function (): void {
    $this->seed(DepartmentAndMunicipalitySeeder::class);

    $totalCount = Municipality::count();
    $uniqueCodeCount = Municipality::distinct('code')->count('code');

    expect($totalCount)->toBe($uniqueCodeCount);
});

test('coordinates are correctly parsed with dot decimal', function (): void {
    $this->seed(DepartmentAndMunicipalitySeeder::class);

    $medellin = Municipality::where('code', '5001')->first();

    expect($medellin->latitude)->not->toBeNull();
    expect($medellin->longitude)->not->toBeNull();
    expect((float) $medellin->latitude)->toBeGreaterThan(0);
    expect((float) $medellin->longitude)->toBeLessThan(0);
    expect(str_contains((string) $medellin->latitude, ','))->toBeFalse();
    expect(str_contains((string) $medellin->longitude, ','))->toBeFalse();
});
