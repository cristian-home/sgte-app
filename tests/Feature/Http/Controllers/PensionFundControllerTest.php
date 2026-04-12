<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\PensionFund;
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
    $pensionFunds = PensionFund::factory()->count(3)->create();

    $response = get(route('pension-funds.index'));

    $response->assertOk();
});

test('index returns inertia page with pension funds', function (): void {
    PensionFund::factory()->count(5)->create();

    $response = get(route('pension-funds.index'));

    $response->assertInertia(
        fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('pension-funds/index')
            ->has('pensionFunds', 10)
    );
});

test('create behaves as expected', function (): void {
    $response = get(route('pension-funds.create'));

    $response->assertOk();
});

test('store uses form request validation')
    ->assertActionUsesFormRequest(
        \App\Http\Controllers\PensionFundController::class,
        'store',
        \App\Http\Requests\PensionFundStoreRequest::class
    );

test('store saves and redirects', function (): void {
    $code = fake()->lexify('???');
    $name = fake()->name();

    $response = post(route('pension-funds.store'), [
        'code' => $code,
        'name' => $name,
    ]);

    $pensionFunds = PensionFund::query()
        ->where('code', $code)
        ->where('name', $name)
        ->get();
    expect($pensionFunds)->toHaveCount(1);

    $response->assertRedirect(route('pension-funds.index'));
});

test('show behaves as expected', function (): void {
    $pensionFund = PensionFund::factory()->create();

    $response = get(route('pension-funds.show', $pensionFund));

    $response->assertOk();
});

test('edit behaves as expected', function (): void {
    $pensionFund = PensionFund::factory()->create();

    $response = get(route('pension-funds.edit', $pensionFund));

    $response->assertOk();
});

test('update uses form request validation')
    ->assertActionUsesFormRequest(
        \App\Http\Controllers\PensionFundController::class,
        'update',
        \App\Http\Requests\PensionFundUpdateRequest::class
    );

test('update redirects', function (): void {
    $pensionFund = PensionFund::factory()->create();
    $code = fake()->lexify('???');
    $name = fake()->name();

    $response = put(route('pension-funds.update', $pensionFund), [
        'code' => $code,
        'name' => $name,
    ]);

    $pensionFund->refresh();

    $response->assertRedirect(route('pension-funds.index'));

    expect($code)->toEqual($pensionFund->code);
    expect($name)->toEqual($pensionFund->name);
});

test('destroy deletes and redirects', function (): void {
    $pensionFund = PensionFund::factory()->create();

    $response = delete(route('pension-funds.destroy', $pensionFund));

    $response->assertRedirect(route('pension-funds.index'));

    assertSoftDeleted($pensionFund);
});
