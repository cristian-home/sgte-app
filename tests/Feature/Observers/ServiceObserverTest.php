<?php

use App\Enums\DayStatusEnum;
use App\Models\DayStatus;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function (): void {
    SpatieRole::create(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);
});

test('creating the first service for a date auto-creates a DayStatus with projected status', function (): void {
    $date = Carbon::today()->toDateString();

    Service::factory()->create(['service_date' => $date]);

    $dayStatus = DayStatus::whereDate('date', $date)->first();

    expect($dayStatus)->not->toBeNull();
    expect($dayStatus->status)->toBe(DayStatusEnum::Projected);
});

test('creating a second service for the same date does not create a duplicate DayStatus', function (): void {
    $date = Carbon::today()->toDateString();

    Service::factory()->create(['service_date' => $date]);
    Service::factory()->create(['service_date' => $date]);

    $count = DayStatus::whereDate('date', $date)->count();

    expect($count)->toBe(1);
});

test('creating a service for a different date creates a separate DayStatus', function (): void {
    $date1 = Carbon::today()->toDateString();
    $date2 = Carbon::tomorrow()->toDateString();

    Service::factory()->create(['service_date' => $date1]);
    Service::factory()->create(['service_date' => $date2]);

    expect(DayStatus::whereDate('date', $date1)->count())->toBe(1);
    expect(DayStatus::whereDate('date', $date2)->count())->toBe(1);
});

test('deleting the last service for a date removes the DayStatus record', function (): void {
    $date = Carbon::today()->toDateString();

    $service = Service::factory()->create(['service_date' => $date]);

    expect(DayStatus::whereDate('date', $date)->count())->toBe(1);

    $service->delete();

    expect(DayStatus::whereDate('date', $date)->count())->toBe(0);
});

test('deleting a service when other services remain on the same date does not remove the DayStatus', function (): void {
    $date = Carbon::today()->toDateString();

    $service1 = Service::factory()->create(['service_date' => $date]);
    Service::factory()->create(['service_date' => $date]);

    $service1->delete();

    expect(DayStatus::whereDate('date', $date)->count())->toBe(1);
});
