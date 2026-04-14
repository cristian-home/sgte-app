<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Contract;
use App\Models\ThirdParty;
use App\Models\User;
use Illuminate\Support\Carbon;

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

test('store preserves user-supplied contract_number even when is_generic is true', function (): void {
    $third_party = ThirdParty::factory()->create();

    $response = post(route('contracts.store'), [
        'contract_number' => 'SPECIAL-2026-001',
        'third_party_id' => $third_party->id,
        'contract_object' => 'business',
        'start_date' => Carbon::now()->toDateString(),
        'end_date' => Carbon::now()->addYear()->toDateString(),
        'route_description' => fake()->sentence(),
        'is_generic' => true,
        'active' => true,
    ]);

    $response->assertRedirect(route('contracts.index'));
    $contract = Contract::query()->where('contract_number', 'SPECIAL-2026-001')->first();
    expect($contract)->not->toBeNull();
    expect($contract->is_generic)->toBeTrue();
});

test('index returns paginated payload with third-party relations', function (): void {
    Contract::query()->delete();
    ThirdParty::query()->delete();

    Contract::factory()->count(3)->create();

    $response = get(route('contracts.index'));
    $response->assertOk();

    $page = $response->viewData('page');
    $contracts = $page['props']['contracts'];

    expect($contracts)->toHaveKey('data');
    expect($contracts)->toHaveKey('per_page');
    expect($contracts)->toHaveKey('current_page');
    expect($contracts)->toHaveKey('total');
    expect($contracts['data'])->toHaveCount(3);

    foreach ($contracts['data'] as $row) {
        expect($row)->toHaveKey('third_party');
    }
});

test('index passes customer options for the create modal and the combobox filter', function (): void {
    Contract::query()->delete();
    ThirdParty::query()->delete();

    ThirdParty::factory()->create(['is_customer' => true, 'is_provider' => false]);
    ThirdParty::factory()->create(['is_customer' => true, 'is_provider' => true]);
    ThirdParty::factory()->create(['is_customer' => false, 'is_provider' => true]);

    $response = get(route('contracts.index'));
    $response->assertOk();

    $options = $response->viewData('page')['props']['thirdParties'];
    expect(count($options))->toBe(2);
    foreach ($options as $opt) {
        expect($opt['is_customer'])->toBeTrue();
    }
});

test('index filters by contract_status = vigente', function (): void {
    Contract::query()->delete();

    $customer = ThirdParty::factory()->create(['is_customer' => true]);
    $vigente = Contract::factory()->create([
        'third_party_id' => $customer->id,
        'active' => true,
        'start_date' => Carbon::today()->subMonth(),
        'end_date' => Carbon::today()->addMonths(6),
    ]);
    Contract::factory()->create([
        'third_party_id' => $customer->id,
        'active' => true,
        'start_date' => Carbon::today()->subMonths(6),
        'end_date' => Carbon::today()->subDays(5),
    ]);
    Contract::factory()->create([
        'third_party_id' => $customer->id,
        'active' => true,
        'start_date' => Carbon::today()->subMonth(),
        'end_date' => Carbon::today()->addDays(20),
    ]);
    Contract::factory()->create([
        'third_party_id' => $customer->id,
        'active' => false,
        'start_date' => Carbon::today()->subMonth(),
        'end_date' => Carbon::today()->addMonths(6),
    ]);

    $response = get(route('contracts.index', ['filter' => ['contract_status' => 'vigente']]));
    $response->assertOk();

    $rows = $response->viewData('page')['props']['contracts']['data'];
    expect($rows)->toHaveCount(1);
    expect($rows[0]['id'])->toBe($vigente->id);
});

test('index filters by contract_status = por_vencer', function (): void {
    Contract::query()->delete();

    $customer = ThirdParty::factory()->create(['is_customer' => true]);
    $inWindow = Contract::factory()->create([
        'third_party_id' => $customer->id,
        'active' => true,
        'start_date' => Carbon::today()->subMonth(),
        'end_date' => Carbon::today()->addDays(45),
    ]);
    Contract::factory()->create([
        'third_party_id' => $customer->id,
        'active' => true,
        'start_date' => Carbon::today()->subMonth(),
        'end_date' => Carbon::today()->addDays(120),
    ]);
    Contract::factory()->create([
        'third_party_id' => $customer->id,
        'active' => true,
        'start_date' => Carbon::today()->subMonths(6),
        'end_date' => Carbon::today()->subDays(5),
    ]);

    $response = get(route('contracts.index', ['filter' => ['contract_status' => 'por_vencer']]));
    $response->assertOk();

    $rows = $response->viewData('page')['props']['contracts']['data'];
    expect($rows)->toHaveCount(1);
    expect($rows[0]['id'])->toBe($inWindow->id);
});

test('index filters by contract_status = vencido and excludes inactive', function (): void {
    Contract::query()->delete();

    $customer = ThirdParty::factory()->create(['is_customer' => true]);
    $vencido = Contract::factory()->create([
        'third_party_id' => $customer->id,
        'active' => true,
        'start_date' => Carbon::today()->subMonths(6),
        'end_date' => Carbon::today()->subDays(3),
    ]);
    Contract::factory()->create([
        'third_party_id' => $customer->id,
        'active' => false,
        'start_date' => Carbon::today()->subMonths(6),
        'end_date' => Carbon::today()->subDays(3),
    ]);

    $response = get(route('contracts.index', ['filter' => ['contract_status' => 'vencido']]));
    $response->assertOk();

    $rows = $response->viewData('page')['props']['contracts']['data'];
    expect($rows)->toHaveCount(1);
    expect($rows[0]['id'])->toBe($vencido->id);
});

test('index filters by contract_status = inactivo', function (): void {
    Contract::query()->delete();

    $customer = ThirdParty::factory()->create(['is_customer' => true]);
    $inactivo = Contract::factory()->create([
        'third_party_id' => $customer->id,
        'active' => false,
        'start_date' => Carbon::today()->subMonth(),
        'end_date' => Carbon::today()->addMonths(6),
    ]);
    Contract::factory()->create([
        'third_party_id' => $customer->id,
        'active' => true,
        'start_date' => Carbon::today()->subMonth(),
        'end_date' => Carbon::today()->addMonths(6),
    ]);

    $response = get(route('contracts.index', ['filter' => ['contract_status' => 'inactivo']]));
    $response->assertOk();

    $rows = $response->viewData('page')['props']['contracts']['data'];
    expect($rows)->toHaveCount(1);
    expect($rows[0]['id'])->toBe($inactivo->id);
});

test('index aliases expiring_soon to por_vencer', function (): void {
    Contract::query()->delete();

    $customer = ThirdParty::factory()->create(['is_customer' => true]);
    $target = Contract::factory()->create([
        'third_party_id' => $customer->id,
        'active' => true,
        'start_date' => Carbon::today()->subMonth(),
        'end_date' => Carbon::today()->addDays(30),
    ]);
    Contract::factory()->create([
        'third_party_id' => $customer->id,
        'active' => true,
        'start_date' => Carbon::today()->subMonth(),
        'end_date' => Carbon::today()->addDays(120),
    ]);

    $response = get(route('contracts.index', ['filter' => ['contract_status' => 'expiring_soon']]));
    $response->assertOk();

    $rows = $response->viewData('page')['props']['contracts']['data'];
    expect($rows)->toHaveCount(1);
    expect($rows[0]['id'])->toBe($target->id);
});

test('index aliases expired to vencido', function (): void {
    Contract::query()->delete();

    $customer = ThirdParty::factory()->create(['is_customer' => true]);
    $target = Contract::factory()->create([
        'third_party_id' => $customer->id,
        'active' => true,
        'start_date' => Carbon::today()->subMonths(6),
        'end_date' => Carbon::today()->subDays(3),
    ]);
    Contract::factory()->create([
        'third_party_id' => $customer->id,
        'active' => true,
        'start_date' => Carbon::today()->subMonth(),
        'end_date' => Carbon::today()->addMonths(6),
    ]);

    $response = get(route('contracts.index', ['filter' => ['contract_status' => 'expired']]));
    $response->assertOk();

    $rows = $response->viewData('page')['props']['contracts']['data'];
    expect($rows)->toHaveCount(1);
    expect($rows[0]['id'])->toBe($target->id);
});

test('index filters by contract_object exact', function (): void {
    Contract::query()->delete();

    $customer = ThirdParty::factory()->create(['is_customer' => true]);
    $business = Contract::factory()->create([
        'third_party_id' => $customer->id,
        'contract_object' => \App\Enums\ContractObject::Business,
    ]);
    Contract::factory()->create([
        'third_party_id' => $customer->id,
        'contract_object' => \App\Enums\ContractObject::Tourism,
    ]);

    $response = get(route('contracts.index', ['filter' => ['contract_object' => 'business']]));
    $response->assertOk();

    $rows = $response->viewData('page')['props']['contracts']['data'];
    expect($rows)->toHaveCount(1);
    expect($rows[0]['id'])->toBe($business->id);
});

test('index filters by third_party_id exact', function (): void {
    Contract::query()->delete();

    $customerA = ThirdParty::factory()->create(['is_customer' => true]);
    $customerB = ThirdParty::factory()->create(['is_customer' => true]);
    $wanted = Contract::factory()->create(['third_party_id' => $customerA->id]);
    Contract::factory()->create(['third_party_id' => $customerB->id]);

    $response = get(route('contracts.index', ['filter' => ['third_party_id' => $customerA->id]]));
    $response->assertOk();

    $rows = $response->viewData('page')['props']['contracts']['data'];
    expect($rows)->toHaveCount(1);
    expect($rows[0]['id'])->toBe($wanted->id);
});
