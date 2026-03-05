<?php

use App\Enums\DayStatusEnum;
use App\Enums\ServiceStatus;
use App\Models\DayStatus;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;

use function Pest\Laravel\post;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('admin');
    $this->actingAs($user);
});

test('executing a day with all services closed succeeds', function (): void {
    $date = Carbon::today()->toDateString();
    $dayStatus = DayStatus::factory()->create(['date' => $date, 'status' => DayStatusEnum::Projected]);

    Service::factory()->create(['service_date' => $date, 'service_status' => ServiceStatus::Closed]);
    Service::factory()->create(['service_date' => $date, 'service_status' => ServiceStatus::Closed]);

    $response = post(route('day-statuses.execute', $dayStatus));

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Día ejecutado correctamente.');

    $dayStatus->refresh();
    expect($dayStatus->status)->toBe(DayStatusEnum::Executed);
    expect($dayStatus->executor_id)->not->toBeNull();
    expect($dayStatus->executed_at)->not->toBeNull();
});

test('executing a day with an open service fails', function (): void {
    $date = Carbon::today()->toDateString();
    $dayStatus = DayStatus::factory()->create(['date' => $date, 'status' => DayStatusEnum::Projected]);

    Service::factory()->create(['service_date' => $date, 'service_status' => ServiceStatus::Closed]);
    Service::factory()->create(['service_date' => $date, 'service_status' => ServiceStatus::Open]);

    $response = post(route('day-statuses.execute', $dayStatus));

    $response->assertRedirect();
    $response->assertSessionHas('error', 'No se puede ejecutar el día. Existen servicios abiertos.');

    $dayStatus->refresh();
    expect($dayStatus->status)->toBe(DayStatusEnum::Projected);
});

test('executing a day with no services fails', function (): void {
    $date = Carbon::today()->toDateString();
    $dayStatus = DayStatus::factory()->create(['date' => $date, 'status' => DayStatusEnum::Projected]);

    $response = post(route('day-statuses.execute', $dayStatus));

    $response->assertRedirect();
    $response->assertSessionHas('error', 'No se puede ejecutar un día sin servicios.');
});

test('unauthorized user cannot execute a day', function (): void {
    $user = User::factory()->create();
    $user->assignRole('accounting');
    $this->actingAs($user);

    $date = Carbon::today()->toDateString();
    $dayStatus = DayStatus::factory()->create(['date' => $date, 'status' => DayStatusEnum::Projected]);
    Service::factory()->create(['service_date' => $date, 'service_status' => ServiceStatus::Closed]);

    $response = post(route('day-statuses.execute', $dayStatus));

    $response->assertForbidden();
});

test('executing an already-executed day is idempotent', function (): void {
    $date = Carbon::today()->toDateString();
    $dayStatus = DayStatus::factory()->create([
        'date' => $date,
        'status' => DayStatusEnum::Executed,
        'executor_id' => User::factory(),
        'executed_at' => now()->subHour(),
    ]);

    Service::factory()->create(['service_date' => $date, 'service_status' => ServiceStatus::Closed]);

    $response = post(route('day-statuses.execute', $dayStatus));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $dayStatus->refresh();
    expect($dayStatus->status)->toBe(DayStatusEnum::Executed);
});
