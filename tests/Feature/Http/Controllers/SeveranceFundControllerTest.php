<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\SeveranceFund;
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
    $severanceFunds = SeveranceFund::factory()->count(3)->create();

    $response = get(route('severance-funds.index'));

    $response->assertOk();
});

test('create behaves as expected', function (): void {
    $response = get(route('severance-funds.create'));

    $response->assertOk();
});

test('store uses form request validation')
    ->assertActionUsesFormRequest(
        \App\Http\Controllers\SeveranceFundController::class,
        'store',
        \App\Http\Requests\SeveranceFundStoreRequest::class
    );

test('store saves and redirects', function (): void {
    $code = fake()->lexify('???');
    $name = fake()->name();

    $response = post(route('severance-funds.store'), [
        'code' => $code,
        'name' => $name,
    ]);

    $severanceFunds = SeveranceFund::query()
        ->where('code', $code)
        ->where('name', $name)
        ->get();
    expect($severanceFunds)->toHaveCount(1);

    $response->assertRedirect(route('severance-funds.index'));
});

test('show behaves as expected', function (): void {
    $severanceFund = SeveranceFund::factory()->create();

    $response = get(route('severance-funds.show', $severanceFund));

    $response->assertOk();
});

test('edit behaves as expected', function (): void {
    $severanceFund = SeveranceFund::factory()->create();

    $response = get(route('severance-funds.edit', $severanceFund));

    $response->assertOk();
});

test('update uses form request validation')
    ->assertActionUsesFormRequest(
        \App\Http\Controllers\SeveranceFundController::class,
        'update',
        \App\Http\Requests\SeveranceFundUpdateRequest::class
    );

test('update redirects', function (): void {
    $severanceFund = SeveranceFund::factory()->create();
    $code = fake()->lexify('???');
    $name = fake()->name();

    $response = put(route('severance-funds.update', $severanceFund), [
        'code' => $code,
        'name' => $name,
    ]);

    $severanceFund->refresh();

    $response->assertRedirect(route('severance-funds.index'));

    expect($code)->toEqual($severanceFund->code);
    expect($name)->toEqual($severanceFund->name);
});

test('destroy deletes and redirects', function (): void {
    $severanceFund = SeveranceFund::factory()->create();

    $response = delete(route('severance-funds.destroy', $severanceFund));

    $response->assertRedirect(route('severance-funds.index'));

    assertSoftDeleted($severanceFund);
});
