<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\DocumentType;
use App\Models\Municipality;
use App\Models\ThirdParty;
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
    $thirdParties = ThirdParty::factory()->count(3)->create();

    $response = get(route('third-parties.index'));

    $response->assertOk();
});

test('create behaves as expected', function (): void {
    $response = get(route('third-parties.create'));

    $response->assertOk();
});

test('create page includes municipalities with department relation', function (): void {
    $municipality = Municipality::factory()->create();

    $response = get(route('third-parties.create'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('municipalities')
        ->where('municipalities.0.department.id', $municipality->department_id)
    );
});

test('store uses form request validation')
    ->assertActionUsesFormRequest(
        \App\Http\Controllers\ThirdPartyController::class,
        'store',
        \App\Http\Requests\ThirdPartyStoreRequest::class
    );

test('store saves natural person and redirects', function (): void {
    $document_type = DocumentType::factory()->create();

    $response = post(route('third-parties.store'), [
        'document_type_id' => $document_type->id,
        'identification_number' => fake()->numerify('##########'),
        'is_natural_person' => true,
        'first_name' => fake()->firstName(),
        'first_lastname' => fake()->lastName(),
        'municipality_id' => \App\Models\Municipality::factory()->create()->id,
        'address' => fake()->streetAddress(),
        'phone' => fake()->numerify('3#########'),
        'email' => fake()->safeEmail(),
        'is_customer' => true,
        'is_provider' => false,
        'active' => true,
    ]);

    $response->assertRedirect(route('third-parties.index'));
    expect(ThirdParty::query()->where('document_type_id', $document_type->id)->count())->toBe(1);
});

test('store saves company and redirects', function (): void {
    $document_type = DocumentType::factory()->create();

    $response = post(route('third-parties.store'), [
        'document_type_id' => $document_type->id,
        'identification_number' => fake()->numerify('##########'),
        'is_natural_person' => false,
        'company_name' => fake()->company(),
        'municipality_id' => \App\Models\Municipality::factory()->create()->id,
        'address' => fake()->streetAddress(),
        'phone' => fake()->numerify('3#########'),
        'email' => fake()->safeEmail(),
        'is_customer' => false,
        'is_provider' => true,
        'active' => true,
    ]);

    $response->assertRedirect(route('third-parties.index'));
    expect(ThirdParty::query()->where('document_type_id', $document_type->id)->count())->toBe(1);
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

test('edit page includes municipalities with department relation', function (): void {
    $thirdParty = ThirdParty::factory()->create();
    $municipality = Municipality::factory()->create();

    $response = get(route('third-parties.edit', $thirdParty));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('municipalities')
    );
});

test('update uses form request validation')
    ->assertActionUsesFormRequest(
        \App\Http\Controllers\ThirdPartyController::class,
        'update',
        \App\Http\Requests\ThirdPartyUpdateRequest::class
    );

test('update redirects', function (): void {
    $thirdParty = ThirdParty::factory()->create(['is_natural_person' => true, 'first_name' => 'Juan', 'first_lastname' => 'Pérez']);
    $newName = fake()->firstName();

    $response = put(route('third-parties.update', $thirdParty), [
        'document_type_id' => $thirdParty->document_type_id,
        'identification_number' => $thirdParty->identification_number,
        'is_natural_person' => true,
        'first_name' => $newName,
        'first_lastname' => $thirdParty->first_lastname,
        'municipality_id' => $thirdParty->municipality_id,
        'address' => $thirdParty->address,
        'phone' => $thirdParty->phone,
        'email' => $thirdParty->email,
        'is_customer' => true,
        'is_provider' => false,
        'active' => true,
    ]);

    $thirdParty->refresh();

    $response->assertRedirect(route('third-parties.index'));
    expect($newName)->toEqual($thirdParty->first_name);
});

test('destroy deletes and redirects', function (): void {
    $thirdParty = ThirdParty::factory()->create();

    $response = delete(route('third-parties.destroy', $thirdParty));

    $response->assertRedirect(route('third-parties.index'));

    assertSoftDeleted($thirdParty);
});

test('store natural person fails without first_name', function (): void {
    $response = post(route('third-parties.store'), [
        'document_type_id' => DocumentType::factory()->create()->id,
        'identification_number' => fake()->numerify('##########'),
        'is_natural_person' => true,
        'first_lastname' => fake()->lastName(),
        'municipality_id' => \App\Models\Municipality::factory()->create()->id,
        'address' => fake()->streetAddress(),
        'phone' => fake()->numerify('3#########'),
        'email' => fake()->safeEmail(),
        'is_customer' => true,
        'is_provider' => false,
        'active' => true,
    ]);

    $response->assertSessionHasErrors(['first_name']);
});

test('store company fails without company_name', function (): void {
    $response = post(route('third-parties.store'), [
        'document_type_id' => DocumentType::factory()->create()->id,
        'identification_number' => fake()->numerify('##########'),
        'is_natural_person' => false,
        'municipality_id' => \App\Models\Municipality::factory()->create()->id,
        'address' => fake()->streetAddress(),
        'phone' => fake()->numerify('3#########'),
        'email' => fake()->safeEmail(),
        'is_customer' => true,
        'is_provider' => false,
        'active' => true,
    ]);

    $response->assertSessionHasErrors(['company_name']);
});
