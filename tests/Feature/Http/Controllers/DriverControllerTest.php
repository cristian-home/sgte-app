<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\Role;
use App\Models\DocumentType;
use App\Models\Driver;
use App\Models\Eps;
use App\Models\Municipality;
use App\Models\PensionFund;
use App\Models\Service;
use App\Models\SeveranceFund;
use App\Models\User;
use App\Notifications\DriverAccountInvitationNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\assertSoftDeleted;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

function makeDriverPayload(array $overrides = []): array
{
    return array_merge([
        'document_type_id' => DocumentType::factory()->create()->id,
        'identification_number' => fake()->unique()->numerify('##########'),
        'first_name' => 'Juan',
        'second_name' => null,
        'first_lastname' => 'Pérez',
        'second_lastname' => null,
        'municipality_id' => Municipality::factory()->create()->id,
        'address' => fake()->streetAddress(),
        'phone' => fake()->numerify('3#########'),
        'email' => fake()->unique()->safeEmail(),
        'license_category' => 'C1',
        'license_due_date' => Carbon::now()->addYear()->toDateString(),
        'eps_id' => Eps::factory()->create()->id,
        'pension_fund_id' => PensionFund::factory()->create()->id,
        'severance_fund_id' => SeveranceFund::factory()->create()->id,
        'has_social_security' => true,
        'active' => true,
    ], $overrides);
}

beforeEach(function (): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);
});

test('index behaves as expected', function (): void {
    $drivers = Driver::factory()->count(3)->create();

    $response = get(route('drivers.index'));

    $response->assertOk();
});

test('index returns a paginated payload', function (): void {
    Driver::factory()->count(3)->create();

    $response = get(route('drivers.index'));

    $response->assertOk();
    $response->assertInertia(
        fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('drivers/index')
            ->has('drivers.data', 3)
            ->has('drivers.per_page')
            ->has('drivers.current_page')
            ->has('drivers.total')
    );
});

test('index passes catalog data needed by the create modal', function (): void {
    DocumentType::factory()->create();
    Eps::factory()->create();
    PensionFund::factory()->create();
    SeveranceFund::factory()->create();
    Municipality::factory()->create();

    $response = get(route('drivers.index'));

    $response->assertOk();
    $response->assertInertia(
        fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->has('municipalities')
            ->has('documentTypes')
            ->has('eps')
            ->has('pensionFunds')
            ->has('severanceFunds')
    );
});

test('index filters by license_status expired', function (): void {
    $today = Carbon::today();

    $expired = Driver::factory()->create([
        'license_due_date' => $today->copy()->subDay(),
    ]);

    Driver::factory()->create([
        'license_due_date' => $today->copy()->addDays(15),
    ]);

    Driver::factory()->create([
        'license_due_date' => $today->copy()->addYear(),
    ]);

    $response = get(route('drivers.index', ['filter' => ['license_status' => 'expired']]));

    $response->assertOk();
    $response->assertInertia(
        fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->has('drivers.data', 1)
            ->where('drivers.data.0.id', $expired->id)
    );
});

test('index filters by license_status expiring_soon', function (): void {
    $today = Carbon::today();

    Driver::factory()->create([
        'license_due_date' => $today->copy()->subDay(),
    ]);

    $expiringSoon = Driver::factory()->create([
        'license_due_date' => $today->copy()->addDays(15),
    ]);

    Driver::factory()->create([
        'license_due_date' => $today->copy()->addYear(),
    ]);

    $response = get(route('drivers.index', ['filter' => ['license_status' => 'expiring_soon']]));

    $response->assertOk();
    $response->assertInertia(
        fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->has('drivers.data', 1)
            ->where('drivers.data.0.id', $expiringSoon->id)
    );
});

test('index filters by license_status ok', function (): void {
    $today = Carbon::today();

    Driver::factory()->create([
        'license_due_date' => $today->copy()->subDay(),
    ]);

    Driver::factory()->create([
        'license_due_date' => $today->copy()->addDays(15),
    ]);

    $ok = Driver::factory()->create([
        'license_due_date' => $today->copy()->addYear(),
    ]);

    $response = get(route('drivers.index', ['filter' => ['license_status' => 'ok']]));

    $response->assertOk();
    $response->assertInertia(
        fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->has('drivers.data', 1)
            ->where('drivers.data.0.id', $ok->id)
    );
});

test('index filters by has_social_security', function (): void {
    $with = Driver::factory()->create(['has_social_security' => true]);
    Driver::factory()->create(['has_social_security' => false]);

    $response = get(route('drivers.index', ['filter' => ['has_social_security' => '1']]));

    $response->assertOk();
    $response->assertInertia(
        fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->has('drivers.data', 1)
            ->where('drivers.data.0.id', $with->id)
    );
});

test('license_status and has_social_security filters compose via AND', function (): void {
    $today = Carbon::today();

    // Expired but has SS
    Driver::factory()->create([
        'license_due_date' => $today->copy()->subDay(),
        'has_social_security' => true,
    ]);

    // Expired AND no SS — the only one we expect to match
    $expectedMatch = Driver::factory()->create([
        'license_due_date' => $today->copy()->subDay(),
        'has_social_security' => false,
    ]);

    // OK + no SS
    Driver::factory()->create([
        'license_due_date' => $today->copy()->addYear(),
        'has_social_security' => false,
    ]);

    $response = get(route('drivers.index', [
        'filter' => [
            'license_status' => 'expired',
            'has_social_security' => '0',
        ],
    ]));

    $response->assertOk();
    $response->assertInertia(
        fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->has('drivers.data', 1)
            ->where('drivers.data.0.id', $expectedMatch->id)
    );
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
        ->where('eps_id', $eps->id)
        ->where('pension_fund_id', $pension_fund->id)
        ->where('severance_fund_id', $severance_fund->id)
        ->where('has_social_security', $has_social_security)
        ->where('active', $active)
        ->get();
    expect($drivers)->toHaveCount(1);
    $driver = $drivers->first();
    expect($driver->license_due_date)->toBe($license_due_date->format('Y-m-d'));
    expect($driver->user_id)->toBeNull();

    $response->assertRedirect(route('drivers.show', $driver));
});

test('show behaves as expected', function (): void {
    $driver = Driver::factory()->create();

    $response = get(route('drivers.show', $driver));

    $response->assertOk();
});

test('show returns driver with relationships and recent services', function (): void {
    $municipality = Municipality::factory()->create();
    $driver = Driver::factory()->create([
        'municipality_id' => $municipality->id,
    ]);

    Service::factory()->count(7)->sequence(
        ['service_date' => Carbon::today()->subDays(1)],
        ['service_date' => Carbon::today()->subDays(2)],
        ['service_date' => Carbon::today()->subDays(3)],
        ['service_date' => Carbon::today()->subDays(4)],
        ['service_date' => Carbon::today()->subDays(5)],
        ['service_date' => Carbon::today()->subDays(6)],
        ['service_date' => Carbon::today()->subDays(7)],
    )->create([
        'driver_id' => $driver->id,
    ]);

    $response = get(route('drivers.show', $driver));

    $response->assertOk();
    $response->assertInertia(
        fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('drivers/show')
            ->where('driver.id', $driver->id)
            ->where('driver.municipality.id', $municipality->id)
            ->has('driver.municipality.department')
            ->has('driver.document_type')
            ->has('driver.eps')
            ->has('driver.pension_fund')
            ->has('driver.severance_fund')
            ->has('recentServices', 5)
    );
});

test('show returns empty recentServices when none exist', function (): void {
    $driver = Driver::factory()->create();

    $response = get(route('drivers.show', $driver));

    $response->assertOk();
    $response->assertInertia(
        fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('drivers/show')
            ->has('recentServices', 0)
    );
});

test('show renders user link when user_id is set', function (): void {
    $linkedUser = User::factory()->create(['email' => 'driver@example.test']);
    $driver = Driver::factory()->create(['user_id' => $linkedUser->id]);

    $response = get(route('drivers.show', $driver));

    $response->assertOk();
    $response->assertInertia(
        fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->where('driver.user.id', $linkedUser->id)
            ->where('driver.user.email', 'driver@example.test')
    );
});

test('show renders no user link when user_id is null', function (): void {
    $driver = Driver::factory()->create(['user_id' => null]);

    $response = get(route('drivers.show', $driver));

    $response->assertOk();
    $response->assertInertia(
        fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->where('driver.user', null)
    );
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
    expect($license_due_date->format('Y-m-d'))->toEqual($driver->license_due_date);
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

test('store creates driver without account when create_account is false', function (): void {
    Notification::fake();

    $response = post(route('drivers.store'), makeDriverPayload([
        'create_account' => false,
        'email' => 'sincuenta@sgte.app',
    ]));

    $driver = Driver::query()->where('email', 'sincuenta@sgte.app')->first();
    expect($driver)->not->toBeNull();
    expect($driver->user_id)->toBeNull();
    expect(User::query()->where('email', 'sincuenta@sgte.app')->exists())->toBeFalse();

    Notification::assertNothingSent();
    $response->assertRedirect(route('drivers.show', $driver));
});

test('store creates driver with linked user and sends invitation when create_account is true', function (): void {
    Notification::fake();

    $response = post(route('drivers.store'), makeDriverPayload([
        'first_name' => 'Carlos',
        'second_name' => null,
        'first_lastname' => 'Mejía',
        'second_lastname' => null,
        'email' => 'driver-contacto@sgte.app',
        'create_account' => true,
        'account_email' => 'driver-cuenta@sgte.app',
    ]));

    $driver = Driver::query()->where('email', 'driver-contacto@sgte.app')->first();
    expect($driver)->not->toBeNull();
    expect($driver->user_id)->not->toBeNull();

    $user = $driver->user;
    expect($user)->not->toBeNull();
    expect($user->email)->toBe('driver-cuenta@sgte.app');
    expect($user->name)->toBe('Carlos Mejía');
    expect($user->must_change_password)->toBeTrue();
    expect($user->is_active)->toBeTrue();
    expect($user->hasRole(Role::DRIVER->value))->toBeTrue();

    Notification::assertSentTo($user, DriverAccountInvitationNotification::class);
    $response->assertRedirect(route('drivers.show', $driver));
});

test('store rejects when account_email is missing and create_account is true', function (): void {
    $response = post(route('drivers.store'), makeDriverPayload([
        'create_account' => true,
        // account_email omitido
    ]));

    $response->assertSessionHasErrors(['account_email']);
    expect(Driver::query()->count())->toBe(0);
});

test('store rejects when account_email already exists in users', function (): void {
    User::factory()->create(['email' => 'taken@sgte.app']);

    $response = post(route('drivers.store'), makeDriverPayload([
        'create_account' => true,
        'account_email' => 'taken@sgte.app',
    ]));

    $response->assertSessionHasErrors(['account_email']);
    expect(Driver::query()->count())->toBe(0);
});

test('update rejects new email when it collides with an unrelated user', function (): void {
    $linkedUser = User::factory()->create(['email' => 'linked@sgte.app']);
    $driver = Driver::factory()->create([
        'user_id' => $linkedUser->id,
        'email' => 'linked@sgte.app',
    ]);

    // Otro user que no es el del driver
    User::factory()->create(['email' => 'someone-else@sgte.app']);

    $response = put(route('drivers.update', $driver), [
        'document_type_id' => $driver->document_type_id,
        'identification_number' => $driver->identification_number,
        'first_name' => $driver->first_name,
        'first_lastname' => $driver->first_lastname,
        'municipality_id' => $driver->municipality_id,
        'address' => $driver->address,
        'phone' => $driver->phone,
        'email' => 'someone-else@sgte.app',
        'license_category' => 'C1',
        'license_due_date' => Carbon::now()->addYear()->toDateString(),
        'eps_id' => $driver->eps_id,
        'pension_fund_id' => $driver->pension_fund_id,
        'severance_fund_id' => $driver->severance_fund_id,
        'has_social_security' => true,
        'active' => true,
    ]);

    $response->assertSessionHasErrors(['email']);
});

test('invite-account creates a user, links the driver and sends the invitation', function (): void {
    Notification::fake();

    $driver = Driver::factory()->create([
        'user_id' => null,
        'first_name' => 'Mario',
        'first_lastname' => 'Castro',
        'email' => 'mario-contacto@sgte.app',
    ]);

    $response = post(route('drivers.invite-account', $driver), [
        'account_email' => 'mario-cuenta@sgte.app',
    ]);

    $driver->refresh();
    expect($driver->user_id)->not->toBeNull();
    expect($driver->user->email)->toBe('mario-cuenta@sgte.app');
    expect($driver->user->hasRole(Role::DRIVER->value))->toBeTrue();

    Notification::assertSentTo($driver->user, DriverAccountInvitationNotification::class);
    $response->assertRedirect(route('drivers.show', $driver));
});

test('invite-account rejects when driver already has an account', function (): void {
    $existing = User::factory()->create();
    $driver = Driver::factory()->create(['user_id' => $existing->id]);

    $response = post(route('drivers.invite-account', $driver), [
        'account_email' => 'otro@sgte.app',
    ]);

    $response->assertSessionHasErrors(['account_email']);
});

test('invite-account rejects when email is taken by another user', function (): void {
    $driver = Driver::factory()->create(['user_id' => null]);
    User::factory()->create(['email' => 'taken-too@sgte.app']);

    $response = post(route('drivers.invite-account', $driver), [
        'account_email' => 'taken-too@sgte.app',
    ]);

    $response->assertSessionHasErrors(['account_email']);
    expect($driver->fresh()->user_id)->toBeNull();
});

test('resend-invitation re-notifies an already-linked driver', function (): void {
    Notification::fake();

    $user = User::factory()->create();
    $user->assignRole(Role::DRIVER->value);
    $driver = Driver::factory()->create(['user_id' => $user->id]);

    $response = post(route('drivers.resend-invitation', $driver));

    Notification::assertSentTo($user, DriverAccountInvitationNotification::class);
    $response->assertRedirect(route('drivers.show', $driver));
});

test('resend-invitation rejects when driver has no account', function (): void {
    $driver = Driver::factory()->create(['user_id' => null]);

    $response = post(route('drivers.resend-invitation', $driver));

    $response->assertSessionHasErrors(['driver']);
});

test('show payload includes user is_active when account exists', function (): void {
    $user = User::factory()->create(['is_active' => false]);
    $driver = Driver::factory()->create(['user_id' => $user->id]);

    get(route('drivers.show', $driver))->assertInertia(
        fn ($page) => $page
            ->where('driver.user.id', $user->id)
            ->where('driver.user.is_active', false)
    );
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
