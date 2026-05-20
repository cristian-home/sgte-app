<?php

namespace Tests\Feature\Http;

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * The standalone create/edit pages were replaced by in-page modals. The
 * legacy `/{resource}/create` and `/{resource}/{id}/edit` URLs now
 * redirect to the resource index (where the modal lives) instead of
 * falling through to the resource `show` route — which would 500 on
 * Postgres when route-model binding tries to cast "create" to a bigint.
 */
dataset('modal_resources', [
    'document-types',
    'eps',
    'pension-funds',
    'severance-funds',
    'third-parties',
    'drivers',
    'vehicles',
    'contracts',
    'invoices',
    'incident-types',
]);

beforeEach(function (): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    actingAs($user);
});

test('legacy create URL redirects to the resource index', function (string $resource): void {
    get("/{$resource}/create")->assertRedirect(route("{$resource}.index"));
})->with('modal_resources');

test('legacy edit URL redirects to the resource index', function (string $resource): void {
    get("/{$resource}/123/edit")->assertRedirect(route("{$resource}.index"));
})->with('modal_resources');

test('bare edit URL redirects to the resource index', function (string $resource): void {
    get("/{$resource}/edit")->assertRedirect(route("{$resource}.index"));
})->with('modal_resources');
