<?php

use App\Models\Department;
use App\Models\Municipality;

test('department can be created via factory', function (): void {
    $department = Department::factory()->create();

    expect($department)->toBeInstanceOf(Department::class);
    expect($department->exists)->toBeTrue();
});

test('municipalities relationship returns Municipality instances', function (): void {
    $department = Department::factory()->create();
    Municipality::factory()->count(3)->create(['department_id' => $department->id]);

    $municipalities = $department->municipalities;

    expect($municipalities)->toHaveCount(3);
    expect($municipalities->first())->toBeInstanceOf(Municipality::class);
});
