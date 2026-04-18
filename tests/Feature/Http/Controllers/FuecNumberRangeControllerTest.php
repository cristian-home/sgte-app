<?php

use App\Enums\Role;
use App\Models\FuecNumberRange;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function (): void {
    config()->set('sgte.fuec_enabled', true);
});

test('admin can list, create, and show a FuecNumberRange', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::ADMIN->value);
    actingAs($admin);

    get(route('fuec-number-ranges.index'))->assertOk();
    get(route('fuec-number-ranges.create'))->assertOk();

    post(route('fuec-number-ranges.store'), [
        'resolution_number' => 'RES-ADMIN',
        'resolution_year' => 2026,
        'range_from' => 1,
        'range_to' => 100,
        'active' => true,
        'notes' => 'Prueba desde test.',
    ])->assertRedirect(route('fuec-number-ranges.index'));

    $range = FuecNumberRange::where('resolution_number', 'RES-ADMIN')->first();
    expect($range)->not->toBeNull();

    get(route('fuec-number-ranges.show', $range))->assertOk();
});

test('store rejects range_to <= range_from', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::ADMIN->value);
    actingAs($admin);

    post(route('fuec-number-ranges.store'), [
        'resolution_number' => 'RES-BAD',
        'resolution_year' => 2026,
        'range_from' => 100,
        'range_to' => 50,
        'active' => false,
    ])->assertSessionHasErrors('range_to');
});

test('activating a new range deactivates the previous one', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::ADMIN->value);
    actingAs($admin);

    $first = FuecNumberRange::factory()->active()->create();

    post(route('fuec-number-ranges.store'), [
        'resolution_number' => 'RES-NEW',
        'resolution_year' => 2027,
        'range_from' => 10000,
        'range_to' => 20000,
        'active' => true,
    ])->assertRedirect();

    $first->refresh();
    expect($first->active)->toBeFalse();

    $new = FuecNumberRange::where('resolution_number', 'RES-NEW')->first();
    expect($new->active)->toBeTrue();
});

test('update transitions active/inactive correctly', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::ADMIN->value);
    actingAs($admin);

    $range = FuecNumberRange::factory()->create(['active' => false]);

    put(route('fuec-number-ranges.update', $range), [
        'resolution_number' => $range->resolution_number,
        'resolution_year' => $range->resolution_year,
        'range_from' => $range->range_from,
        'range_to' => $range->range_to,
        'active' => true,
    ])->assertRedirect();

    $range->refresh();
    expect($range->active)->toBeTrue();
});

test('destroy blocks deletion of ranges with associated FUECs', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::ADMIN->value);
    actingAs($admin);

    $range = FuecNumberRange::factory()->create();
    $range->fuecs()->create([
        'uuid' => \Illuminate\Support\Str::uuid(),
        'service_id' => \App\Models\Service::factory()->create()->id,
        'consecutive_number' => 1,
        'generated_at' => now(),
        'qr_code' => (string) \Illuminate\Support\Str::uuid(),
        'status' => 'active',
        'pdf_disk' => 's3',
    ]);

    delete(route('fuec-number-ranges.destroy', $range))
        ->assertSessionHasErrors('fuecs');

    expect(FuecNumberRange::query()->find($range->id))->not->toBeNull();
});

test('non-admin receives 403', function (): void {
    $operator = User::factory()->create();
    $operator->assignRole(Role::OPERATOR->value);
    actingAs($operator);

    get(route('fuec-number-ranges.index'))->assertForbidden();
});
