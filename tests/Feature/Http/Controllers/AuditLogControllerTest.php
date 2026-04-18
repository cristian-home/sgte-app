<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\Role;
use App\Models\Service;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * Pull the Inertia props off a test response. We can't use `->json()`
 * because Inertia returns HTML unless the request carries the matching
 * `X-Inertia-Version` header (which we don't know at test time); instead
 * we go through the AssertableInertia helper and capture `toArray()`.
 *
 * @return array<string, mixed>
 */
function auditLogProps(\Illuminate\Testing\TestResponse $response): array
{
    $captured = [];
    $response->assertInertia(function (\Inertia\Testing\AssertableInertia $page) use (&$captured): void {
        $captured = $page->toArray()['props'];
    });

    return $captured;
}

test('admin can view the audit log index', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::ADMIN->value);
    actingAs($admin);

    Vehicle::factory()->create();

    $response = get(route('audit-log.index'));

    $response->assertOk();
    $response->assertInertia(
        fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('audit-log/index')
            ->has('activities')
            ->has('users')
            ->has('subjectTypes')
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

test('unauthenticated users are redirected from the audit log', function (): void {
    get(route('audit-log.index'))->assertRedirect(route('login'));
});

test('index returns paginated payload with users and subjectTypes props', function (): void {
    $admin = User::factory()->create(['name' => 'Admin Tester']);
    $admin->assignRole(Role::ADMIN->value);
    actingAs($admin);

    Vehicle::factory()->count(3)->create();

    get(route('audit-log.index'))
        ->assertInertia(
            fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('audit-log/index')
                ->has('activities.data')
                ->has('activities.per_page')
                ->has('activities.current_page')
                ->has('activities.total')
                ->has('activities.links')
                ->has('users.0', fn ($user) => $user
                    ->has('id')
                    ->has('name')
                    ->has('email')
                )
                ->has('subjectTypes')
        );
});

test('index projects the properties bag including justification and edited_on_executed_day', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::ADMIN->value);
    actingAs($admin);

    $service = Service::factory()->create();
    // Wipe factory-triggered activity noise so our manual log lands at data.0.
    Activity::query()->delete();

    activity()
        ->performedOn($service)
        ->causedBy($admin)
        ->withProperties([
            'justification' => 'Servicio reabierto por error administrativo confirmado.',
            'edited_on_executed_day' => true,
        ])
        ->log('Servicio editado en día ejecutado');

    get(route('audit-log.index'))
        ->assertInertia(
            fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('audit-log/index')
                ->has(
                    'activities.data.0',
                    fn ($activity) => $activity
                        ->where('properties.justification', 'Servicio reabierto por error administrativo confirmado.')
                        ->where('properties.edited_on_executed_day', true)
                        ->etc()
                )
        );
});

test('index projects attributes and old_attributes from the properties bag for diff rendering', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::ADMIN->value);
    actingAs($admin);

    $service = Service::factory()->create();
    Activity::query()->delete();

    activity()
        ->performedOn($service)
        ->causedBy($admin)
        ->withProperties([
            'attributes' => ['unit_value' => 100000],
            'old' => ['unit_value' => 50000],
        ])
        ->log('updated');

    get(route('audit-log.index'))
        ->assertInertia(
            fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('audit-log/index')
                ->has(
                    'activities.data.0',
                    fn ($activity) => $activity
                        ->where('attributes.unit_value', 100000)
                        ->where('old_attributes.unit_value', 50000)
                        ->etc()
                )
        );
});

test('index filters by subject_type exact', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::ADMIN->value);
    actingAs($admin);

    Vehicle::factory()->create();
    Service::factory()->create();

    $props = auditLogProps(get(route('audit-log.index', [
        'filter' => ['subject_type' => Service::class],
    ]))->assertOk());

    $payload = $props['activities']['data'];
    expect($payload)->not->toBeEmpty();
    foreach ($payload as $row) {
        expect($row['subject_type'])->toBe(Service::class);
    }
});

test('index filters by causer_id exact', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::ADMIN->value);
    actingAs($admin);

    $otherAdmin = User::factory()->create();
    $otherAdmin->assignRole(Role::ADMIN->value);

    $service = Service::factory()->create();
    Activity::query()->delete();

    activity()->performedOn($service)->causedBy($admin)->event('created')->log('created');
    activity()->performedOn($service)->causedBy($otherAdmin)->event('updated')->log('updated');

    $props = auditLogProps(get(route('audit-log.index', [
        'filter' => ['causer_id' => $admin->id],
    ]))->assertOk());

    $payload = $props['activities']['data'];
    expect($payload)->not->toBeEmpty();
    foreach ($payload as $row) {
        expect($row['causer']['id'])->toBe($admin->id);
    }
});

test('index filters by event exact', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::ADMIN->value);
    actingAs($admin);

    $service = Service::factory()->create();
    Activity::query()->delete();

    activity()->performedOn($service)->causedBy($admin)->event('created')->log('created');
    activity()->performedOn($service)->causedBy($admin)->event('updated')->log('updated');
    activity()->performedOn($service)->causedBy($admin)->event('deleted')->log('deleted');

    $props = auditLogProps(get(route('audit-log.index', [
        'filter' => ['event' => 'updated'],
    ]))->assertOk());

    $payload = $props['activities']['data'];
    expect($payload)->not->toBeEmpty();
    foreach ($payload as $row) {
        expect($row['event'])->toBe('updated');
    }
});

test('index filters by created_from and created_to date range', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::ADMIN->value);
    actingAs($admin);

    $service = Service::factory()->create();
    Activity::query()->delete();

    $today = Carbon::today();

    $oldActivity = activity()->performedOn($service)->causedBy($admin)->event('old')->log('old');
    Activity::query()->where('id', $oldActivity->id)->update([
        'created_at' => $today->copy()->subDays(5),
    ]);

    $midActivity = activity()->performedOn($service)->causedBy($admin)->event('mid')->log('mid');
    Activity::query()->where('id', $midActivity->id)->update([
        'created_at' => $today->copy()->subDays(2),
    ]);

    $nowActivity = activity()->performedOn($service)->causedBy($admin)->event('now')->log('now');
    Activity::query()->where('id', $nowActivity->id)->update([
        'created_at' => $today->copy(),
    ]);

    $props = auditLogProps(get(route('audit-log.index', [
        'filter' => [
            'created_from' => $today->copy()->subDays(3)->toDateString(),
            'created_to' => $today->copy()->subDays(1)->toDateString(),
        ],
    ]))->assertOk());

    $events = array_column($props['activities']['data'], 'event');
    expect($events)->toContain('mid');
    expect($events)->not->toContain('old');
    expect($events)->not->toContain('now');
});

test('index filters created_from and created_to ignore empty strings', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::ADMIN->value);
    actingAs($admin);

    Vehicle::factory()->create();

    get(route('audit-log.index', [
        'filter' => ['created_from' => '', 'created_to' => ''],
    ]))->assertOk();
});

test('subjectTypes is dynamically computed from distinct subject_type values in the last 1000 activity rows', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::ADMIN->value);
    actingAs($admin);

    Vehicle::factory()->create();
    Service::factory()->create();

    $props = auditLogProps(get(route('audit-log.index'))->assertOk());

    $options = $props['subjectTypes'];

    $values = array_column($options, 'value');
    expect($values)->toContain(Vehicle::class);
    expect($values)->toContain(Service::class);

    foreach ($options as $entry) {
        expect($entry)->toHaveKeys(['value', 'label']);
    }

    $byValue = array_column($options, 'label', 'value');
    expect($byValue[Vehicle::class] ?? null)->toBe('Vehículo');
    expect($byValue[Service::class] ?? null)->toBe('Servicio');
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
                ->has(
                    'activities.data',
                    fn ($activities) => $activities->each(
                        fn ($activity) => $activity
                            ->has('id')
                            ->has('description')
                            ->has('event')
                            ->has('subject_type')
                            ->has('subject_id')
                            ->has('log_name')
                            ->has('causer')
                            ->has('created_at')
                            ->has('properties')
                            ->has('attributes')
                            ->has('old_attributes')
                    )
                )
        );
});
