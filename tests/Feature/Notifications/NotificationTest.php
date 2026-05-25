<?php

namespace Tests\Feature\Notifications;

use App\Models\DayStatus;
use App\Models\Driver;
use App\Models\Service;
use App\Models\ServiceIncident;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\BillingIncidentNotification;
use App\Notifications\DayExecutedNotification;
use App\Notifications\DocumentExpirationNotification;
use App\Notifications\LicenseExpirationNotification;
use App\Notifications\ServiceAssignedNotification;
use Illuminate\Support\Facades\Notification;

test('ServiceAssignedNotification can be rendered', function (): void {
    $service = Service::factory()->create();
    $notification = new ServiceAssignedNotification($service);
    $user = User::factory()->create();

    $mail = $notification->toMail($user);

    expect($mail->subject)->toContain('Servicio Asignado');
});

test('DocumentExpirationNotification can be rendered', function (): void {
    $vehicle = Vehicle::factory()->create();
    $notification = new DocumentExpirationNotification($vehicle, 'SOAT', 15);
    $user = User::factory()->create();

    $mail = $notification->toMail($user);

    expect($mail->subject)->toContain('Documento por vencer');
});

test('LicenseExpirationNotification can be rendered', function (): void {
    $driver = Driver::factory()->create();
    $notification = new LicenseExpirationNotification($driver, 5);
    $user = User::factory()->create();

    $mail = $notification->toMail($user);

    expect($mail->subject)->toContain('Licencia por vencer');
});

test('BillingIncidentNotification can be rendered', function (): void {
    $incident = ServiceIncident::factory()->create(['affects_billing' => true, 'additional_value' => 50000]);
    $incident->load(['service', 'incidentType']);
    $notification = new BillingIncidentNotification($incident);
    $user = User::factory()->create();

    $mail = $notification->toMail($user);

    expect($mail->subject)->toContain('afectación a facturación');
});

test('DayExecutedNotification can be rendered', function (): void {
    $dayStatus = DayStatus::factory()->create();
    $notification = new DayExecutedNotification($dayStatus);
    $user = User::factory()->create();

    $mail = $notification->toMail($user);

    expect($mail->subject)->toContain('Día Ejecutado');
});

test('ServiceAssignedNotification is dispatched to driver user', function (): void {
    Notification::fake();

    $driverUser = User::factory()->create();
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
    $service = Service::factory()->create(['driver_id' => $driver->id]);
    $service->load('vehicle');

    $driverUser->notify(new ServiceAssignedNotification($service));

    Notification::assertSentTo($driverUser, ServiceAssignedNotification::class);
});

test('billing incident notifies admins and accounting', function (): void {
    Notification::fake();

    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    $service = Service::factory()->create();
    $incidentType = \App\Models\IncidentType::factory()->create();

    $this->post(route('service-incidents.store'), [
        'service_id' => $service->id,
        'incident_type_id' => $incidentType->id,
        'description' => 'Test billing incident',
        'affects_billing' => true,
        'additional_value' => 100000,
    ]);

    Notification::assertSentTo($user, BillingIncidentNotification::class);
});

test('check-expirations command runs successfully', function (): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    $this->artisan('app:check-expirations')
        ->assertExitCode(0);
});

test('check-expirations sends notification for expiring vehicle document', function (): void {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Vehicle::factory()->create([
        'soat_due_date' => today()->addDays(15),
    ]);

    $this->artisan('app:check-expirations')->assertExitCode(0);

    Notification::assertSentTo($admin, DocumentExpirationNotification::class);
});

test('check-expirations sends notification for expiring driver license', function (): void {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Driver::factory()->create([
        'license_due_date' => today()->addDays(5),
        'active' => true,
    ]);

    $this->artisan('app:check-expirations')->assertExitCode(0);

    Notification::assertSentTo($admin, LicenseExpirationNotification::class);
});
