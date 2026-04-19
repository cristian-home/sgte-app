<?php

use App\Jobs\ScanThirdPartyVehicleDocuments;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:check-expirations')->dailyAt('07:00');

// REQ-004 third-party fleet document reminder sweep
// (third-party-vehicle-doc-reminders). Runs daily at 06:30 — before
// the internal admin-focused app:check-expirations run — so provider
// outreach can start early in the day.
Schedule::job(new ScanThirdPartyVehicleDocuments)
    ->dailyAt('06:30')
    ->onOneServer()
    ->name('scan-third-party-vehicle-documents');
