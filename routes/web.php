<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
})->name('home');

Route::get('dashboard', [DashboardController::class, 'show'])->middleware(['auth', 'verified', 'can:dashboard.view'])->name('dashboard');

// Public FUEC verification endpoint (REQ-007 AC#4) — scanned from the
// QR embedded on the PDF. No auth; gated only by the feature flag.
Route::get('fuec/verify/{uuid}', [App\Http\Controllers\FuecVerifyController::class, 'show'])
    ->middleware('fuec.enabled')
    ->name('fuec.verify');

require __DIR__.'/settings.php';

Route::middleware(['auth', 'verified'])->group(function () {
    // Driver dashboard
    Route::get('driver', [App\Http\Controllers\DriverDashboardController::class, 'index'])->name('driver.dashboard');
    Route::post('driver/services/{service}/confirm-start', [App\Http\Controllers\DriverDashboardController::class, 'confirmStart'])->name('driver.confirm-start');
    Route::post('driver/services/{service}/confirm-end', [App\Http\Controllers\DriverDashboardController::class, 'confirmEnd'])->name('driver.confirm-end');
    Route::post('driver/services/{service}/decline', [App\Http\Controllers\DriverDashboardController::class, 'decline'])->name('driver.decline');

    Route::get('gantt', [App\Http\Controllers\GanttController::class, 'index'])->name('gantt.index');
    Route::get('day-summary/export', [App\Http\Controllers\DaySummaryController::class, 'export'])->name('day-summary.export');
    Route::get('day-summary', [App\Http\Controllers\DaySummaryController::class, 'index'])->name('day-summary.index');
    Route::resource('document-types', App\Http\Controllers\DocumentTypeController::class);
    Route::resource('eps', App\Http\Controllers\EpsController::class);
    Route::resource('pension-funds', App\Http\Controllers\PensionFundController::class);
    Route::resource('severance-funds', App\Http\Controllers\SeveranceFundController::class);
    Route::resource('third-parties', App\Http\Controllers\ThirdPartyController::class);
    Route::resource('drivers', App\Http\Controllers\DriverController::class);
    Route::resource('vehicles', App\Http\Controllers\VehicleController::class);
    Route::resource('contracts', App\Http\Controllers\ContractController::class);
    Route::post('invoices/{invoice}/mark-paid', [App\Http\Controllers\InvoiceController::class, 'markPaid'])->name('invoices.mark-paid');
    Route::get('invoices/{invoice}/pdf', [App\Http\Controllers\InvoiceController::class, 'pdf'])->middleware('can:'.App\Enums\Permission::VIEW_INVOICES->value)->name('invoices.pdf');
    Route::post('invoices/{invoice}/services', [App\Http\Controllers\InvoiceController::class, 'attachServices'])->middleware('can:'.App\Enums\Permission::ASSIGN_SERVICES_TO_INVOICES->value)->name('invoices.services.attach');
    Route::delete('invoices/{invoice}/services/{service}', [App\Http\Controllers\InvoiceController::class, 'detachService'])->middleware('can:'.App\Enums\Permission::ASSIGN_SERVICES_TO_INVOICES->value)->name('invoices.services.detach');
    Route::post('invoices/{invoice}/recompute-total', [App\Http\Controllers\InvoiceController::class, 'recomputeTotal'])->middleware('can:'.App\Enums\Permission::ASSIGN_SERVICES_TO_INVOICES->value)->name('invoices.recompute-total');
    Route::resource('invoices', App\Http\Controllers\InvoiceController::class);
    Route::get('day-statuses/{year}/{month}', [App\Http\Controllers\DayStatusController::class, 'calendarMonth'])
        ->name('day-statuses.calendar-month')
        ->where(['year' => '20[2-9][0-9]', 'month' => '[1-9]|1[0-2]']);
    Route::get('day-statuses/{year}', [App\Http\Controllers\DayStatusController::class, 'calendar'])
        ->name('day-statuses.calendar')
        ->where('year', '20[2-9][0-9]');
    Route::post('day-statuses/{day_status}/execute', [App\Http\Controllers\DayStatusController::class, 'execute'])->name('day-statuses.execute');
    Route::resource('day-statuses', App\Http\Controllers\DayStatusController::class);
    Route::resource('services', App\Http\Controllers\ServiceController::class);
    Route::resource('incident-types', App\Http\Controllers\IncidentTypeController::class);
    Route::resource('service-incidents', App\Http\Controllers\ServiceIncidentController::class);
    // FUEC module (REQ-007) — gated behind the sgte.fuec_enabled feature
    // flag so the whole group 404s when the module is disabled.
    Route::middleware('fuec.enabled')->group(function (): void {
        Route::get('fuecs/candidate-services', [App\Http\Controllers\FuecController::class, 'candidateServices'])
            ->middleware('can:'.App\Enums\Permission::GENERATE_FUEC->value)
            ->name('fuecs.candidate-services');
        Route::get('fuecs/{fuec}/pdf', [App\Http\Controllers\FuecController::class, 'pdf'])
            ->middleware('can:'.App\Enums\Permission::VIEW_FUEC->value)
            ->name('fuecs.pdf');
        Route::post('fuecs/{fuec}/cancel', [App\Http\Controllers\FuecController::class, 'cancel'])
            ->middleware('can:'.App\Enums\Permission::GENERATE_FUEC->value)
            ->name('fuecs.cancel');
        Route::resource('fuecs', App\Http\Controllers\FuecController::class)
            ->only(['index', 'create', 'store', 'show']);
        Route::resource('fuec-number-ranges', App\Http\Controllers\FuecNumberRangeController::class)
            ->middleware('can:'.App\Enums\Permission::MANAGE_FUEC_NUMBER_RANGES->value);
    });

    // GPS / Vehicle Locations (REQ-010) — gated behind the sgte.gps_enabled
    // feature flag so the group 404s when the module is disabled.
    Route::middleware('gps.enabled')->group(function (): void {
        Route::get('gps/map', [App\Http\Controllers\VehicleLocationMapController::class, 'index'])
            ->middleware('can:'.App\Enums\Permission::VIEW_VEHICLE_LOCATIONS->value)
            ->name('gps.map');
        Route::resource('vehicle-locations', App\Http\Controllers\VehicleLocationController::class);
        Route::post('driver/services/{service}/location', [App\Http\Controllers\DriverLocationController::class, 'store'])
            ->middleware('can:'.App\Enums\Permission::REGISTER_VEHICLE_LOCATION->value)
            ->name('driver.location.store');
    });

    // Administración (admin only)
    Route::resource('users', App\Http\Controllers\UserController::class);
    Route::get('audit-log', [App\Http\Controllers\AuditLogController::class, 'index'])->name('audit-log.index');
});
