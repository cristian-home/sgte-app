<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\DocumentType;
use App\Models\Driver;
use App\Models\Eps;
use App\Models\Municipality;
use App\Models\PensionFund;
use App\Models\SeveranceFund;
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
    $drivers = Driver::factory()->count(3)->create();

    $response = get(route('drivers.index'));

    $response->assertOk();
});

test('create behaves as expected', function (): void {
    $response = get(route('drivers.create'));

    $response->assertOk();
});

test('create page includes municipalities with department relation', function (): void {
    $municipality = Municipality::factory()->create();

    $response = get(route('drivers.create'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('municipalities')
        ->where('municipalities.0.department.id', $municipality->department_id)
    );
});

test('store uses form request validation')
    ->assertActionUsesFormRequest(
        \App\Http\Controllers\DriverController::class,
        'store',
        \App\Http\Requests\DriverStoreRequest::class
    );

test('store saves and redirects', function (): void {
    $document_type = DocumentType::factory()->create();
    $identification_number = fake()->word();
    $first_name = fake()->firstName();
    $second_name = fake()->word();
    $first_lastname = fake()->word();
    $second_lastname = fake()->word();
    $municipality = \App\Models\Municipality::factory()->create();
    $address = fake()->word();
    $phone = fake()->phoneNumber();
    $email = fake()->safeEmail();
    $license_category = fake()->randomElement(['C1', 'C2', 'C3']);
    $license_due_date = Carbon::parse(fake()->dateTimeBetween('+1 month', '+3 years'));
    $eps = Eps::factory()->create();
    $pension_fund = PensionFund::factory()->create();
    $severance_fund = SeveranceFund::factory()->create();
    $has_social_security = fake()->boolean();
    $active = fake()->boolean();

    $response = post(route('drivers.store'), [
        'document_type_id' => $document_type->id,
        'identification_number' => $identification_number,
        'first_name' => $first_name,
        'second_name' => $second_name,
        'first_lastname' => $first_lastname,
        'second_lastname' => $second_lastname,
        'municipality_id' => $municipality->id,
        'address' => $address,
        'phone' => $phone,
        'email' => $email,
        'license_category' => $license_category,
        'license_due_date' => $license_due_date,
        'eps_id' => $eps->id,
        'pension_fund_id' => $pension_fund->id,
        'severance_fund_id' => $severance_fund->id,
        'has_social_security' => $has_social_security,
        'active' => $active,
    ]);

    $drivers = Driver::query()
        ->where('document_type_id', $document_type->id)
        ->where('identification_number', $identification_number)
        ->where('first_name', $first_name)
        ->where('second_name', $second_name)
        ->where('first_lastname', $first_lastname)
        ->where('second_lastname', $second_lastname)
        ->where('municipality_id', $municipality->id)
        ->where('address', $address)
        ->where('phone', $phone)
        ->where('email', $email)
        ->where('license_category', $license_category)
        ->where('license_due_date', $license_due_date)
        ->where('eps_id', $eps->id)
        ->where('pension_fund_id', $pension_fund->id)
        ->where('severance_fund_id', $severance_fund->id)
        ->where('has_social_security', $has_social_security)
        ->where('active', $active)
        ->get();
    expect($drivers)->toHaveCount(1);
    $driver = $drivers->first();

    $response->assertRedirect(route('drivers.index'));
});

test('show behaves as expected', function (): void {
    $driver = Driver::factory()->create();

    $response = get(route('drivers.show', $driver));

    $response->assertOk();
});

test('edit behaves as expected', function (): void {
    $driver = Driver::factory()->create();

    $response = get(route('drivers.edit', $driver));

    $response->assertOk();
});

test('edit page includes municipalities with department relation', function (): void {
    $driver = Driver::factory()->create();
    $municipality = Municipality::factory()->create();

    $response = get(route('drivers.edit', $driver));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('municipalities')
    );
});

test('update uses form request validation')
    ->assertActionUsesFormRequest(
        \App\Http\Controllers\DriverController::class,
        'update',
        \App\Http\Requests\DriverUpdateRequest::class
    );

test('update redirects', function (): void {
    $driver = Driver::factory()->create();
    $document_type = DocumentType::factory()->create();
    $identification_number = fake()->word();
    $first_name = fake()->firstName();
    $second_name = fake()->word();
    $first_lastname = fake()->word();
    $second_lastname = fake()->word();
    $municipality = \App\Models\Municipality::factory()->create();
    $address = fake()->word();
    $phone = fake()->phoneNumber();
    $email = fake()->safeEmail();
    $license_category = fake()->randomElement(['C1', 'C2', 'C3']);
    $license_due_date = Carbon::parse(fake()->date());
    $eps = Eps::factory()->create();
    $pension_fund = PensionFund::factory()->create();
    $severance_fund = SeveranceFund::factory()->create();
    $has_social_security = fake()->boolean();
    $active = fake()->boolean();

    $response = put(route('drivers.update', $driver), [
        'document_type_id' => $document_type->id,
        'identification_number' => $identification_number,
        'first_name' => $first_name,
        'second_name' => $second_name,
        'first_lastname' => $first_lastname,
        'second_lastname' => $second_lastname,
        'municipality_id' => $municipality->id,
        'address' => $address,
        'phone' => $phone,
        'email' => $email,
        'license_category' => $license_category,
        'license_due_date' => $license_due_date,
        'eps_id' => $eps->id,
        'pension_fund_id' => $pension_fund->id,
        'severance_fund_id' => $severance_fund->id,
        'has_social_security' => $has_social_security,
        'active' => $active,
    ]);

    $driver->refresh();

    $response->assertRedirect(route('drivers.index'));

    expect($document_type->id)->toEqual($driver->document_type_id);
    expect($identification_number)->toEqual($driver->identification_number);
    expect($first_name)->toEqual($driver->first_name);
    expect($second_name)->toEqual($driver->second_name);
    expect($first_lastname)->toEqual($driver->first_lastname);
    expect($second_lastname)->toEqual($driver->second_lastname);
    expect($municipality->id)->toEqual($driver->municipality_id);
    expect($address)->toEqual($driver->address);
    expect($phone)->toEqual($driver->phone);
    expect($email)->toEqual($driver->email);
    expect($license_category)->toEqual($driver->license_category->value);
    expect($license_due_date)->toEqual($driver->license_due_date);
    expect($eps->id)->toEqual($driver->eps_id);
    expect($pension_fund->id)->toEqual($driver->pension_fund_id);
    expect($severance_fund->id)->toEqual($driver->severance_fund_id);
    expect($has_social_security)->toEqual($driver->has_social_security);
    expect($active)->toEqual($driver->active);
});

test('destroy deletes and redirects', function (): void {
    $driver = Driver::factory()->create();

    $response = delete(route('drivers.destroy', $driver));

    $response->assertRedirect(route('drivers.index'));

    assertSoftDeleted($driver);
});

test('store fails with invalid license category', function (): void {
    $response = post(route('drivers.store'), [
        'document_type_id' => DocumentType::factory()->create()->id,
        'identification_number' => fake()->numerify('##########'),
        'first_name' => fake()->firstName(),
        'first_lastname' => fake()->lastName(),
        'municipality_id' => \App\Models\Municipality::factory()->create()->id,
        'address' => fake()->streetAddress(),
        'phone' => fake()->numerify('3#########'),
        'email' => fake()->safeEmail(),
        'license_category' => 'X5',
        'license_due_date' => Carbon::now()->addYear()->toDateString(),
        'eps_id' => Eps::factory()->create()->id,
        'pension_fund_id' => PensionFund::factory()->create()->id,
        'severance_fund_id' => SeveranceFund::factory()->create()->id,
        'has_social_security' => true,
        'active' => true,
    ]);

    $response->assertSessionHasErrors(['license_category']);
});

test('store fails with expired license date', function (): void {
    $response = post(route('drivers.store'), [
        'document_type_id' => DocumentType::factory()->create()->id,
        'identification_number' => fake()->numerify('##########'),
        'first_name' => fake()->firstName(),
        'first_lastname' => fake()->lastName(),
        'municipality_id' => \App\Models\Municipality::factory()->create()->id,
        'address' => fake()->streetAddress(),
        'phone' => fake()->numerify('3#########'),
        'email' => fake()->safeEmail(),
        'license_category' => 'C1',
        'license_due_date' => Carbon::now()->subDay()->toDateString(),
        'eps_id' => Eps::factory()->create()->id,
        'pension_fund_id' => PensionFund::factory()->create()->id,
        'severance_fund_id' => SeveranceFund::factory()->create()->id,
        'has_social_security' => true,
        'active' => true,
    ]);

    $response->assertSessionHasErrors(['license_due_date']);
});

test('update allows past license date for existing drivers', function (): void {
    $driver = Driver::factory()->create();

    $response = put(route('drivers.update', $driver), [
        'document_type_id' => $driver->document_type_id,
        'identification_number' => $driver->identification_number,
        'first_name' => $driver->first_name,
        'first_lastname' => $driver->first_lastname,
        'municipality_id' => $driver->municipality_id,
        'address' => $driver->address,
        'phone' => $driver->phone,
        'email' => $driver->email,
        'license_category' => 'C1',
        'license_due_date' => Carbon::now()->subMonth()->toDateString(),
        'eps_id' => $driver->eps_id,
        'pension_fund_id' => $driver->pension_fund_id,
        'severance_fund_id' => $driver->severance_fund_id,
        'has_social_security' => true,
        'active' => true,
    ]);

    $response->assertRedirect(route('drivers.index'));
});
