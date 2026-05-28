<?php

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('guests are redirected to login', function () {
    get(route('about'))->assertRedirect(route('login'));
});

test('authenticated users see the about page', function () {
    actingAs(User::factory()->create());

    get(route('about'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('about')
            ->where('config.version', config('app.version'))
            ->where('config.environment', config('app.env'))
        );
});

test('config.version reads from the VERSION file in the repo root', function () {
    $expected = trim((string) @file_get_contents(base_path('VERSION'))) ?: 'dev';

    expect(config('app.version'))->toBe($expected);
});
