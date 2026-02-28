<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\DocumentType;
use App\Models\ThirdParty;
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
    $thirdParties = ThirdParty::factory()->count(3)->create();

    $response = get(route('third-parties.index'));

    $response->assertOk();
});

test('create behaves as expected', function (): void {
    $response = get(route('third-parties.create'));

    $response->assertOk();
});

test('store uses form request validation')
    ->assertActionUsesFormRequest(
        \App\Http\Controllers\ThirdPartyController::class,
        'store',
        \App\Http\Requests\ThirdPartyStoreRequest::class
    );

test('store saves and redirects', function (): void {
    $document_type = DocumentType::factory()->create();
    $identification_number = fake()->word();
    $is_natural_person = fake()->boolean();
    $first_name = fake()->firstName();
    $second_name = fake()->word();
    $first_lastname = fake()->word();
    $second_lastname = fake()->word();
    $company_name = fake()->word();
    $trade_name = fake()->word();
    $city = fake()->city();
    $address = fake()->word();
    $phone = fake()->phoneNumber();
    $email = fake()->safeEmail();
    $is_customer = fake()->boolean();
    $is_provider = fake()->boolean();
    $active = fake()->boolean();

    $response = post(route('third-parties.store'), [
        'document_type_id' => $document_type->id,
        'identification_number' => $identification_number,
        'is_natural_person' => $is_natural_person,
        'first_name' => $first_name,
        'second_name' => $second_name,
        'first_lastname' => $first_lastname,
        'second_lastname' => $second_lastname,
        'company_name' => $company_name,
        'trade_name' => $trade_name,
        'city' => $city,
        'address' => $address,
        'phone' => $phone,
        'email' => $email,
        'is_customer' => $is_customer,
        'is_provider' => $is_provider,
        'active' => $active,
    ]);

    $thirdParties = ThirdParty::query()
        ->where('document_type_id', $document_type->id)
        ->where('identification_number', $identification_number)
        ->where('is_natural_person', $is_natural_person)
        ->where('first_name', $first_name)
        ->where('second_name', $second_name)
        ->where('first_lastname', $first_lastname)
        ->where('second_lastname', $second_lastname)
        ->where('company_name', $company_name)
        ->where('trade_name', $trade_name)
        ->where('city', $city)
        ->where('address', $address)
        ->where('phone', $phone)
        ->where('email', $email)
        ->where('is_customer', $is_customer)
        ->where('is_provider', $is_provider)
        ->where('active', $active)
        ->get();
    expect($thirdParties)->toHaveCount(1);
    $thirdParty = $thirdParties->first();

    $response->assertRedirect(route('third-parties.index'));
});

test('show behaves as expected', function (): void {
    $thirdParty = ThirdParty::factory()->create();

    $response = get(route('third-parties.show', $thirdParty));

    $response->assertOk();
});

test('edit behaves as expected', function (): void {
    $thirdParty = ThirdParty::factory()->create();

    $response = get(route('third-parties.edit', $thirdParty));

    $response->assertOk();
});

test('update uses form request validation')
    ->assertActionUsesFormRequest(
        \App\Http\Controllers\ThirdPartyController::class,
        'update',
        \App\Http\Requests\ThirdPartyUpdateRequest::class
    );

test('update redirects', function (): void {
    $thirdParty = ThirdParty::factory()->create();
    $document_type = DocumentType::factory()->create();
    $identification_number = fake()->word();
    $is_natural_person = fake()->boolean();
    $first_name = fake()->firstName();
    $second_name = fake()->word();
    $first_lastname = fake()->word();
    $second_lastname = fake()->word();
    $company_name = fake()->word();
    $trade_name = fake()->word();
    $city = fake()->city();
    $address = fake()->word();
    $phone = fake()->phoneNumber();
    $email = fake()->safeEmail();
    $is_customer = fake()->boolean();
    $is_provider = fake()->boolean();
    $active = fake()->boolean();

    $response = put(route('third-parties.update', $thirdParty), [
        'document_type_id' => $document_type->id,
        'identification_number' => $identification_number,
        'is_natural_person' => $is_natural_person,
        'first_name' => $first_name,
        'second_name' => $second_name,
        'first_lastname' => $first_lastname,
        'second_lastname' => $second_lastname,
        'company_name' => $company_name,
        'trade_name' => $trade_name,
        'city' => $city,
        'address' => $address,
        'phone' => $phone,
        'email' => $email,
        'is_customer' => $is_customer,
        'is_provider' => $is_provider,
        'active' => $active,
    ]);

    $thirdParty->refresh();

    $response->assertRedirect(route('third-parties.index'));

    expect($document_type->id)->toEqual($thirdParty->document_type_id);
    expect($identification_number)->toEqual($thirdParty->identification_number);
    expect($is_natural_person)->toEqual($thirdParty->is_natural_person);
    expect($first_name)->toEqual($thirdParty->first_name);
    expect($second_name)->toEqual($thirdParty->second_name);
    expect($first_lastname)->toEqual($thirdParty->first_lastname);
    expect($second_lastname)->toEqual($thirdParty->second_lastname);
    expect($company_name)->toEqual($thirdParty->company_name);
    expect($trade_name)->toEqual($thirdParty->trade_name);
    expect($city)->toEqual($thirdParty->city);
    expect($address)->toEqual($thirdParty->address);
    expect($phone)->toEqual($thirdParty->phone);
    expect($email)->toEqual($thirdParty->email);
    expect($is_customer)->toEqual($thirdParty->is_customer);
    expect($is_provider)->toEqual($thirdParty->is_provider);
    expect($active)->toEqual($thirdParty->active);
});

test('destroy deletes and redirects', function (): void {
    $thirdParty = ThirdParty::factory()->create();

    $response = delete(route('third-parties.destroy', $thirdParty));

    $response->assertRedirect(route('third-parties.index'));

    assertSoftDeleted($thirdParty);
});
