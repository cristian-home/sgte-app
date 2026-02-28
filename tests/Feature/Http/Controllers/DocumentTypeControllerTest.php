<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\DocumentType;
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
    $documentTypes = DocumentType::factory()->count(3)->create();

    $response = get(route('document-types.index'));

    $response->assertOk();
});

test('create behaves as expected', function (): void {
    $response = get(route('document-types.create'));

    $response->assertOk();
});

test('store uses form request validation')
    ->assertActionUsesFormRequest(
        \App\Http\Controllers\DocumentTypeController::class,
        'store',
        \App\Http\Requests\DocumentTypeStoreRequest::class
    );

test('store saves and redirects', function (): void {
    $code = fake()->lexify('???');
    $name = fake()->name();
    $is_natural_person = fake()->boolean();
    $is_legal_person = fake()->boolean();

    $response = post(route('document-types.store'), [
        'code' => $code,
        'name' => $name,
        'is_natural_person' => $is_natural_person,
        'is_legal_person' => $is_legal_person,
    ]);

    $documentTypes = DocumentType::query()
        ->where('code', $code)
        ->where('name', $name)
        ->where('is_natural_person', $is_natural_person)
        ->where('is_legal_person', $is_legal_person)
        ->get();
    expect($documentTypes)->toHaveCount(1);
    $documentType = $documentTypes->first();

    $response->assertRedirect(route('document-types.index'));
});

test('show behaves as expected', function (): void {
    $documentType = DocumentType::factory()->create();

    $response = get(route('document-types.show', $documentType));

    $response->assertOk();
});

test('edit behaves as expected', function (): void {
    $documentType = DocumentType::factory()->create();

    $response = get(route('document-types.edit', $documentType));

    $response->assertOk();
});

test('update uses form request validation')
    ->assertActionUsesFormRequest(
        \App\Http\Controllers\DocumentTypeController::class,
        'update',
        \App\Http\Requests\DocumentTypeUpdateRequest::class
    );

test('update redirects', function (): void {
    $documentType = DocumentType::factory()->create();
    $code = fake()->lexify('???');
    $name = fake()->name();
    $is_natural_person = fake()->boolean();
    $is_legal_person = fake()->boolean();

    $response = put(route('document-types.update', $documentType), [
        'code' => $code,
        'name' => $name,
        'is_natural_person' => $is_natural_person,
        'is_legal_person' => $is_legal_person,
    ]);

    $documentType->refresh();

    $response->assertRedirect(route('document-types.index'));

    expect($code)->toEqual($documentType->code);
    expect($name)->toEqual($documentType->name);
    expect($is_natural_person)->toEqual($documentType->is_natural_person);
    expect($is_legal_person)->toEqual($documentType->is_legal_person);
});

test('destroy deletes and redirects', function (): void {
    $documentType = DocumentType::factory()->create();

    $response = delete(route('document-types.destroy', $documentType));

    $response->assertRedirect(route('document-types.index'));

    assertSoftDeleted($documentType);
});
