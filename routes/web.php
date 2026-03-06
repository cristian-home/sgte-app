<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
})->name('home');

Route::get('dashboard', [DashboardController::class, 'show'])->middleware(['auth', 'verified', 'can:dashboard.view'])->name('dashboard');

require __DIR__.'/settings.php';

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('gantt', [App\Http\Controllers\GanttController::class, 'index'])->name('gantt.index');
    Route::resource('document-types', App\Http\Controllers\DocumentTypeController::class);
    Route::resource('eps', App\Http\Controllers\EpsController::class);
    Route::resource('pension-funds', App\Http\Controllers\PensionFundController::class);
    Route::resource('severance-funds', App\Http\Controllers\SeveranceFundController::class);
    Route::resource('third-parties', App\Http\Controllers\ThirdPartyController::class);
    Route::resource('drivers', App\Http\Controllers\DriverController::class);
    Route::resource('vehicles', App\Http\Controllers\VehicleController::class);
    Route::resource('contracts', App\Http\Controllers\ContractController::class);
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
    Route::resource('service-incidents', App\Http\Controllers\ServiceIncidentController::class);
    Route::resource('fuecs', App\Http\Controllers\FuecController::class);
    Route::resource('vehicle-locations', App\Http\Controllers\VehicleLocationController::class);
});
