<?php

use App\Enums\DayStatusEnum;
use App\Enums\ServiceStatus;
use App\Models\DayStatus;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

use function Pest\Laravel\get;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('admin');
    $this->actingAs($user);
});

test('index returns calendar data for current year by default', function (): void {
    $response = get(route('day-statuses.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('day-statuses/index')
        ->has('dayStatuses')
        ->has('serviceCounts')
        ->where('year', (int) now()->year)
    );
});

test('index returns data for specific year', function (): void {
    $response = get(route('day-statuses.index', ['year' => 2025]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('year', 2025)
    );
});

test('index rejects invalid year parameter', function (): void {
    $response = get(route('day-statuses.index', ['year' => 1999]));

    $response->assertSessionHasErrors('year');
});

test('index rejects non-numeric year parameter', function (): void {
    $response = get(route('day-statuses.index', ['year' => 'abc']));

    $response->assertSessionHasErrors('year');
});

test('day statuses are keyed by date string', function (): void {
    $date = '2025-06-15';
    DayStatus::factory()->create(['date' => $date, 'status' => DayStatusEnum::Projected]);

    $response = get(route('day-statuses.index', ['year' => 2025]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('dayStatuses.'.$date)
        ->where('dayStatuses.'.$date.'.status', 'projected')
    );
});

test('service counts include total and open_count per date', function (): void {
    $date = '2025-07-10';
    Service::factory()->create(['service_date' => $date, 'service_status' => ServiceStatus::Open]);
    Service::factory()->create(['service_date' => $date, 'service_status' => ServiceStatus::Closed]);
    Service::factory()->create(['service_date' => $date, 'service_status' => ServiceStatus::Open]);

    $response = get(route('day-statuses.index', ['year' => 2025]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('serviceCounts.'.$date)
        ->where('serviceCounts.'.$date.'.total', 3)
        ->where('serviceCounts.'.$date.'.open_count', 2)
    );
});

test('soft-deleted services are excluded from counts', function (): void {
    $date = '2025-08-20';
    Service::factory()->create(['service_date' => $date, 'service_status' => ServiceStatus::Open]);
    Service::factory()->create(['service_date' => $date, 'service_status' => ServiceStatus::Closed, 'deleted_at' => now()]);

    $response = get(route('day-statuses.index', ['year' => 2025]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('serviceCounts.'.$date.'.total', 1)
    );
});

test('only returns data for requested year', function (): void {
    DayStatus::factory()->create(['date' => '2025-03-01', 'status' => DayStatusEnum::Projected]);
    DayStatus::factory()->create(['date' => '2026-03-01', 'status' => DayStatusEnum::Projected]);

    $response = get(route('day-statuses.index', ['year' => 2025]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('dayStatuses.2025-03-01')
        ->missing('dayStatuses.2026-03-01')
    );
});

test('executed day statuses include executor relationship', function (): void {
    $executor = User::factory()->create(['name' => 'Test Executor']);
    DayStatus::factory()->create([
        'date' => '2025-04-15',
        'status' => DayStatusEnum::Executed,
        'executor_id' => $executor->id,
        'executed_at' => now(),
    ]);

    $response = get(route('day-statuses.index', ['year' => 2025]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('dayStatuses.2025-04-15.executor')
        ->where('dayStatuses.2025-04-15.executor.name', 'Test Executor')
    );
});

test('user without VIEW_DAY_SUMMARY permission gets 403', function (): void {
    $user = User::factory()->create();
    $user->assignRole('driver');
    $this->actingAs($user);

    $response = get(route('day-statuses.index'));

    $response->assertForbidden();
});

test('user with VIEW_DAY_SUMMARY permission can access the page', function (): void {
    $response = get(route('day-statuses.index'));

    $response->assertOk();
});
