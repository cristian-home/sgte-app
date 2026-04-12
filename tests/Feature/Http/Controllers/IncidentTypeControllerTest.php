<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\IncidentSeverity;
use App\Models\IncidentType;
use App\Models\User;

use function Pest\Laravel\assertSoftDeleted;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function (): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);
});

test('index behaves as expected', function (): void {
    IncidentType::factory()->count(3)->create();

    $response = get(route('incident-types.index'));

    $response->assertOk();
});

test('index returns inertia page with incident types', function (): void {
    IncidentType::factory()->count(5)->create();

    $response = get(route('incident-types.index'));

    $response->assertInertia(
        fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('incident-types/index')
            ->has('incidentTypes', 12)
    );
});

test('create behaves as expected', function (): void {
    $response = get(route('incident-types.create'));

    $response->assertOk();
});

test('store uses form request validation')
    ->assertActionUsesFormRequest(
        \App\Http\Controllers\IncidentTypeController::class,
        'store',
        \App\Http\Requests\IncidentTypeStoreRequest::class
    );

test('store saves and redirects', function (): void {
    $code = 'RET';
    $name = 'Retraso';
    $severity = IncidentSeverity::Minor->value;

    $response = post(route('incident-types.store'), [
        'code' => $code,
        'name' => $name,
        'severity' => $severity,
        'affects_billing_default' => true,
        'description' => 'Retraso en el servicio',
    ]);

    $incidentType = IncidentType::query()
        ->where('code', $code)
        ->where('name', $name)
        ->get();
    expect($incidentType)->toHaveCount(1);
    expect($incidentType->first()->severity)->toBe(IncidentSeverity::Minor);
    expect($incidentType->first()->affects_billing_default)->toBeTrue();

    $response->assertRedirect(route('incident-types.index'));
});

test('store validates required fields', function (): void {
    $response = post(route('incident-types.store'), []);

    $response->assertSessionHasErrors(['code', 'name', 'severity']);
});

test('store validates unique code', function (): void {
    IncidentType::factory()->create(['code' => 'DUP']);

    $response = post(route('incident-types.store'), [
        'code' => 'DUP',
        'name' => 'Duplicate',
        'severity' => IncidentSeverity::Minor->value,
    ]);

    $response->assertSessionHasErrors(['code']);
});

test('store validates severity enum', function (): void {
    $response = post(route('incident-types.store'), [
        'code' => 'TST',
        'name' => 'Test',
        'severity' => 'invalid',
    ]);

    $response->assertSessionHasErrors(['severity']);
});

test('show behaves as expected', function (): void {
    $incidentType = IncidentType::factory()->create();

    $response = get(route('incident-types.show', $incidentType));

    $response->assertOk();
});

test('edit behaves as expected', function (): void {
    $incidentType = IncidentType::factory()->create();

    $response = get(route('incident-types.edit', $incidentType));

    $response->assertOk();
});

test('update uses form request validation')
    ->assertActionUsesFormRequest(
        \App\Http\Controllers\IncidentTypeController::class,
        'update',
        \App\Http\Requests\IncidentTypeUpdateRequest::class
    );

test('update saves and redirects', function (): void {
    $incidentType = IncidentType::factory()->create();
    $newCode = 'UPD';
    $newName = 'Updated Name';

    $response = put(route('incident-types.update', $incidentType), [
        'code' => $newCode,
        'name' => $newName,
        'severity' => IncidentSeverity::Major->value,
        'affects_billing_default' => false,
        'description' => null,
    ]);

    $incidentType->refresh();

    $response->assertRedirect(route('incident-types.index'));

    expect($newCode)->toEqual($incidentType->code);
    expect($newName)->toEqual($incidentType->name);
    expect($incidentType->severity)->toBe(IncidentSeverity::Major);
});

test('update allows same code for same record', function (): void {
    $incidentType = IncidentType::factory()->create(['code' => 'SAM']);

    $response = put(route('incident-types.update', $incidentType), [
        'code' => 'SAM',
        'name' => 'Same Code',
        'severity' => IncidentSeverity::Minor->value,
    ]);

    $response->assertSessionDoesntHaveErrors(['code']);
    $response->assertRedirect(route('incident-types.index'));
});

test('destroy deletes and redirects', function (): void {
    $incidentType = IncidentType::factory()->create();

    $response = delete(route('incident-types.destroy', $incidentType));

    $response->assertRedirect(route('incident-types.index'));

    assertSoftDeleted($incidentType);
});

test('unauthorized user cannot access index', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = get(route('incident-types.index'));

    $response->assertForbidden();
});

test('unauthorized user cannot store', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = post(route('incident-types.store'), [
        'code' => 'TST',
        'name' => 'Test',
        'severity' => IncidentSeverity::Minor->value,
    ]);

    $response->assertForbidden();
});
