<?php

namespace Tests\Feature\Middleware;

use App\Enums\Role;
use App\Http\Middleware\CaptureViewerTimezone;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\withHeader;
use function Pest\Laravel\withUnencryptedCookie;

beforeEach(function (): void {
    $this->user = User::factory()->create(['is_active' => true]);
    $this->user->assignRole(Role::ADMIN->value);
});

test('header timezone is captured into request attributes and persisted on user', function (): void {
    actingAs($this->user);

    withHeader('X-Viewer-Timezone', 'America/New_York')
        ->get(route('dashboard'))
        ->assertOk();

    expect($this->user->fresh()->timezone)->toBe('America/New_York');
});

test('cookie timezone is captured when no header is present', function (): void {
    actingAs($this->user);

    withUnencryptedCookie('viewer_tz', 'Europe/Madrid')
        ->get(route('dashboard'))
        ->assertOk();

    expect($this->user->fresh()->timezone)->toBe('Europe/Madrid');
});

test('header takes priority over cookie', function (): void {
    actingAs($this->user);

    withHeader('X-Viewer-Timezone', 'Asia/Tokyo')
        ->withUnencryptedCookie('viewer_tz', 'Europe/Madrid')
        ->get(route('dashboard'))
        ->assertOk();

    expect($this->user->fresh()->timezone)->toBe('Asia/Tokyo');
});

test('invalid timezone is silently ignored', function (): void {
    actingAs($this->user);
    $this->user->forceFill(['timezone' => 'America/Bogota'])->save();

    withHeader('X-Viewer-Timezone', 'Mars/Olympus_Mons')
        ->get(route('dashboard'))
        ->assertOk();

    expect($this->user->fresh()->timezone)->toBe('America/Bogota');
});

test('guest request does not crash when timezone is captured', function (): void {
    withHeader('X-Viewer-Timezone', 'America/New_York')
        ->get(route('login'))
        ->assertOk();
});

test('captured timezone is exposed via request attribute', function (): void {
    $captured = null;
    app('router')->get('/_test_capture_tz', function (\Illuminate\Http\Request $request) use (&$captured) {
        $captured = $request->attributes->get(CaptureViewerTimezone::REQUEST_ATTRIBUTE);

        return 'ok';
    })->middleware('web');

    withHeader('X-Viewer-Timezone', 'America/New_York')
        ->get('/_test_capture_tz')
        ->assertOk();

    expect($captured)->toBe('America/New_York');
});
