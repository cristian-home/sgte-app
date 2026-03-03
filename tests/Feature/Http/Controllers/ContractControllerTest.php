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
    $third_party = ThirdParty::factory()->create();
    $start_date = Carbon::now()->addMonth();
    $end_date = Carbon::now()->addYear();

    $response = post(route('contracts.store'), [
        'contract_number' => 'CT-TEST-001',
        'third_party_id' => $third_party->id,
        'contract_object' => 'business',
        'start_date' => $start_date->toDateString(),
        'end_date' => $end_date->toDateString(),
        'route_description' => fake()->sentence(),
        'is_generic' => false,
        'active' => true,
    ]);

    $response->assertRedirect(route('contracts.index'));
    expect(Contract::query()->where('contract_number', 'CT-TEST-001')->count())->toBe(1);
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
    $start_date = Carbon::now()->addMonth();
    $end_date = Carbon::now()->addYear();
    $newNumber = 'CT-UPDATED-001';

    $response = put(route('contracts.update', $contract), [
        'contract_number' => $newNumber,
        'third_party_id' => $contract->third_party_id,
        'contract_object' => $contract->contract_object->value,
        'start_date' => $start_date->toDateString(),
        'end_date' => $end_date->toDateString(),
        'route_description' => $contract->route_description,
        'is_generic' => false,
        'active' => true,
    ]);

    $contract->refresh();

    $response->assertRedirect(route('contracts.index'));
    expect($newNumber)->toEqual($contract->contract_number);
});

test('destroy deletes and redirects', function (): void {
    $contract = Contract::factory()->create();

    $response = delete(route('contracts.destroy', $contract));

    $response->assertRedirect(route('contracts.index'));

    assertSoftDeleted($contract);
});

test('store auto-generates contract number for generic contracts', function (): void {
    $third_party = ThirdParty::factory()->create();

    $response = post(route('contracts.store'), [
        'third_party_id' => $third_party->id,
        'contract_object' => 'occasional',
        'start_date' => Carbon::now()->toDateString(),
        'end_date' => Carbon::now()->addYear()->toDateString(),
        'route_description' => fake()->sentence(),
        'is_generic' => true,
        'active' => true,
    ]);

    $response->assertRedirect(route('contracts.index'));
    $contract = Contract::query()->latest('id')->first();
    expect($contract->contract_number)->toStartWith('GEN-');
    expect($contract->is_generic)->toBeTrue();
});

test('store fails when end_date is before start_date', function (): void {
    $response = post(route('contracts.store'), [
        'contract_number' => 'CT-FAIL-001',
        'third_party_id' => ThirdParty::factory()->create()->id,
        'contract_object' => 'business',
        'start_date' => Carbon::now()->addYear()->toDateString(),
        'end_date' => Carbon::now()->toDateString(),
        'route_description' => fake()->sentence(),
        'is_generic' => false,
        'active' => true,
    ]);

    $response->assertSessionHasErrors(['end_date']);
});
