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

// admin-data-imports: reaper for jobs whose worker died without flipping
// status to failed (OOM, container redeploy mid-run). Runs every 5 min.
Schedule::command('imports:reap-stuck')
    ->everyFiveMinutes()
    ->onOneServer()
    ->name('reap-stuck-imports');

// admin-data-imports: 90-day file retention policy. Row stays forever
// (audit trail), only the MinIO blobs go away.
Schedule::command('imports:purge-old-files')
    ->dailyAt('03:00')
    ->onOneServer()
    ->name('purge-old-import-files');
