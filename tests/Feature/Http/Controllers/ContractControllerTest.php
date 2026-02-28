<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Contract;
use App\Models\ThirdParty;
use App\Models\User;
use Illuminate\Support\Carbon;
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
    $contracts = Contract::factory()->count(3)->create();

    $response = get(route('contracts.index'));

    $response->assertOk();
});

test('create behaves as expected', function (): void {
    $response = get(route('contracts.create'));

    $response->assertOk();
});

test('store uses form request validation')
    ->assertActionUsesFormRequest(
        \App\Http\Controllers\ContractController::class,
        'store',
        \App\Http\Requests\ContractStoreRequest::class
    );

test('store saves and redirects', function (): void {
    $contract_number = fake()->word();
    $third_party = ThirdParty::factory()->create();
    $contract_object = fake()->randomElement(['business', 'tourism', 'health', 'occasional']);
    $start_date = Carbon::parse(fake()->date());
    $end_date = Carbon::parse(fake()->date());
    $route_description = fake()->text();
    $is_generic = fake()->boolean();
    $active = fake()->boolean();

    $response = post(route('contracts.store'), [
        'contract_number' => $contract_number,
        'third_party_id' => $third_party->id,
        'contract_object' => $contract_object,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'route_description' => $route_description,
        'is_generic' => $is_generic,
        'active' => $active,
    ]);

    $contracts = Contract::query()
        ->where('contract_number', $contract_number)
        ->where('third_party_id', $third_party->id)
        ->where('contract_object', $contract_object)
        ->where('start_date', $start_date)
        ->where('end_date', $end_date)
        ->where('route_description', $route_description)
        ->where('is_generic', $is_generic)
        ->where('active', $active)
        ->get();
    expect($contracts)->toHaveCount(1);
    $contract = $contracts->first();

    $response->assertRedirect(route('contracts.index'));
});

test('show behaves as expected', function (): void {
    $contract = Contract::factory()->create();

    $response = get(route('contracts.show', $contract));

    $response->assertOk();
});

test('edit behaves as expected', function (): void {
    $contract = Contract::factory()->create();

    $response = get(route('contracts.edit', $contract));

    $response->assertOk();
});

test('update uses form request validation')
    ->assertActionUsesFormRequest(
        \App\Http\Controllers\ContractController::class,
        'update',
        \App\Http\Requests\ContractUpdateRequest::class
    );

test('update redirects', function (): void {
    $contract = Contract::factory()->create();
    $contract_number = fake()->word();
    $third_party = ThirdParty::factory()->create();
    $contract_object = fake()->randomElement(['business', 'tourism', 'health', 'occasional']);
    $start_date = Carbon::parse(fake()->date());
    $end_date = Carbon::parse(fake()->date());
    $route_description = fake()->text();
    $is_generic = fake()->boolean();
    $active = fake()->boolean();

    $response = put(route('contracts.update', $contract), [
        'contract_number' => $contract_number,
        'third_party_id' => $third_party->id,
        'contract_object' => $contract_object,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'route_description' => $route_description,
        'is_generic' => $is_generic,
        'active' => $active,
    ]);

    $contract->refresh();

    $response->assertRedirect(route('contracts.index'));

    expect($contract_number)->toEqual($contract->contract_number);
    expect($third_party->id)->toEqual($contract->third_party_id);
    expect($contract_object)->toEqual($contract->contract_object);
    expect($start_date)->toEqual($contract->start_date);
    expect($end_date)->toEqual($contract->end_date);
    expect($route_description)->toEqual($contract->route_description);
    expect($is_generic)->toEqual($contract->is_generic);
    expect($active)->toEqual($contract->active);
});

test('destroy deletes and redirects', function (): void {
    $contract = Contract::factory()->create();

    $response = delete(route('contracts.destroy', $contract));

    $response->assertRedirect(route('contracts.index'));

    assertSoftDeleted($contract);
});
