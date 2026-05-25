<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Eps;
use App\Models\Municipality;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

dataset('catalogs', [
    'eps' => ['eps', "code,name\n"],
    'pension-funds' => ['pension-funds', "code,name\n"],
    'severance-funds' => ['severance-funds', "code,name\n"],
    'departments' => ['departments', "code,name\n"],
    'document-types' => ['document-types', "code,name\n"],
    'incident-types' => ['incident-types', "code,name\n"],
]);

test('super admin can export each catalog', function (string $catalog, string $headerLine): void {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN->value);
    actingAs($user);

    $response = get(route('admin.imports.reference.show', $catalog));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($response->streamedContent())->toStartWith($headerLine);
})->with('catalogs');

test('municipalities export includes department code column', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN->value);
    actingAs($user);

    $department = Department::firstOrCreate(['code' => '11'], ['name' => 'Bogotá DC']);
    Municipality::firstOrCreate(
        ['code' => '11001'],
        ['department_id' => $department->id, 'name' => 'Bogotá', 'type' => 'CD'],
    );

    $response = get(route('admin.imports.reference.show', 'municipalities'));
    $response->assertOk();
    $body = $response->streamedContent();

    expect($body)->toStartWith("code,name,department_code\n");
    expect($body)->toContain('11001');
    expect($body)->toContain(',11');
});

test('invalid catalog slug returns 404', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN->value);
    actingAs($user);

    get('/admin/imports/reference/xyz')->assertNotFound();
});

test('non super admin cannot export catalogs', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN->value);
    actingAs($user);

    get(route('admin.imports.reference.show', 'eps'))->assertForbidden();
});

test('eps export contains seeded entries', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN->value);
    actingAs($user);

    Eps::firstOrCreate(['code' => 'EPSXYZ'], ['name' => 'Test EPS']);

    $body = get(route('admin.imports.reference.show', 'eps'))
        ->streamedContent();

    expect($body)->toContain('EPSXYZ');
    expect($body)->toContain('Test EPS');
});
