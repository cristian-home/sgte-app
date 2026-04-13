<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use App\Models\Vehicle;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('admin can view the audit log index', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::ADMIN->value);
    actingAs($admin);

    // Trigger an activity_log entry so the page has something to render.
    Vehicle::factory()->create();

    $response = get(route('audit-log.index'));

    $response->assertOk();
    $response->assertInertia(
        fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('audit-log/index')
            ->has('activities')
    );
});

test('operator cannot view the audit log', function (): void {
    $operator = User::factory()->create();
    $operator->assignRole(Role::OPERATOR->value);
    actingAs($operator);

    get(route('audit-log.index'))->assertForbidden();
});

test('driver cannot view the audit log', function (): void {
    $driver = User::factory()->create();
    $driver->assignRole(Role::DRIVER->value);
    actingAs($driver);

    get(route('audit-log.index'))->assertForbidden();
});

test('accounting cannot view the audit log', function (): void {
    $accounting = User::factory()->create();
    $accounting->assignRole(Role::ACCOUNTING->value);
    actingAs($accounting);

    get(route('audit-log.index'))->assertForbidden();
});

test('audit log exposes causer information when present', function (): void {
    $admin = User::factory()->create(['name' => 'Admin Tester']);
    $admin->assignRole(Role::ADMIN->value);
    actingAs($admin);

    Vehicle::factory()->create();

    get(route('audit-log.index'))
        ->assertInertia(
            fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('audit-log/index')
                ->has('activities', fn ($activities) => $activities->each(
                    fn ($activity) => $activity
                        ->has('id')
                        ->has('description')
                        ->has('event')
                        ->has('subject_type')
                        ->has('subject_id')
                        ->has('log_name')
                        ->has('causer')
                        ->has('created_at')
                ))
        );
});
