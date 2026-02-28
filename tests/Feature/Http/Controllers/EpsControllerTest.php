<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Eps;
use App\Models\User;
use Spatie\Permission\Models\Role as SpatieRole;

use function Pest\Laravel\assertSoftDeleted;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function (): void {
    SpatieRole::create(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);
});

test('index behaves as expected', function (): void {
    $eps = Eps::factory()->count(3)->create();

    $response = get(route('eps.index'));

    $response->assertOk();
});

test('index returns inertia page with eps', function (): void {
    Eps::factory()->count(5)->create();

    $response = get(route('eps.index'));

    $response->assertInertia(
        fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('eps/index')
            ->has('eps', 5)
    );
});

test('create behaves as expected', function (): void {
    $response = get(route('eps.create'));

    $response->assertOk();
});

test('store uses form request validation')
    ->assertActionUsesFormRequest(
        \App\Http\Controllers\EpsController::class,
        'store',
        \App\Http\Requests\EpsStoreRequest::class
    );

test('store saves and redirects', function (): void {
    $code = fake()->lexify('???');
    $name = fake()->name();

    $response = post(route('eps.store'), [
        'code' => $code,
        'name' => $name,
    ]);

    $eps = Eps::query()
        ->where('code', $code)
        ->where('name', $name)
        ->get();
    expect($eps)->toHaveCount(1);

    $response->assertRedirect(route('eps.index'));
});

test('show behaves as expected', function (): void {
    $eps = Eps::factory()->create();

    $response = get(route('eps.show', $eps));

    $response->assertOk();
});

test('edit behaves as expected', function (): void {
    $eps = Eps::factory()->create();

    $response = get(route('eps.edit', $eps));

    $response->assertOk();
});

test('update uses form request validation')
    ->assertActionUsesFormRequest(
        \App\Http\Controllers\EpsController::class,
        'update',
        \App\Http\Requests\EpsUpdateRequest::class
    );

test('update redirects', function (): void {
    $eps = Eps::factory()->create();
    $code = fake()->lexify('???');
    $name = fake()->name();

    $response = put(route('eps.update', $eps), [
        'code' => $code,
        'name' => $name,
    ]);

    $eps->refresh();

    $response->assertRedirect(route('eps.index'));

    expect($code)->toEqual($eps->code);
    expect($name)->toEqual($eps->name);
});

test('destroy deletes and redirects', function (): void {
    $eps = Eps::factory()->create();

    $response = delete(route('eps.destroy', $eps));

    $response->assertRedirect(route('eps.index'));

    assertSoftDeleted($eps);
});
