<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\ServiceStatus;
use App\Enums\VehicleStatus;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Days-ahead window used to flag "por vencer" documents.
     */
    private const EXPIRY_ALERT_DAYS = 30;

    /**
     * Maximum rows returned in the document-alerts panel.
     */
    private const ALERTS_MAX_ROWS = 10;

    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user?->hasRole(Role::DRIVER->value) && ! $user->hasRole(Role::SUPER_ADMIN->value)) {
            return redirect()->route('driver.dashboard');
        }

        $today = Carbon::today();
        $expiryThreshold = $today->copy()->addDays(self::EXPIRY_ALERT_DAYS);

        return Inertia::render('dashboard', [
            'kpis' => [
                'vehicles' => [
                    'total' => Vehicle::count(),
                    'active' => Vehicle::where('status', VehicleStatus::Active->value)->count(),
                    'maintenance' => Vehicle::where('status', VehicleStatus::Maintenance->value)->count(),
                ],
                'drivers' => [
                    'total' => Driver::count(),
                    'active' => Driver::where('active', true)->count(),
                    'inactive' => Driver::where('active', false)->count(),
                ],
                'services_today' => [
                    'total' => Service::whereDate('service_date', $today)->count(),
                    'open' => Service::whereDate('service_date', $today)
                        ->where('service_status', ServiceStatus::Open->value)
                        ->count(),
                    'closed' => Service::whereDate('service_date', $today)
                        ->where('service_status', ServiceStatus::Closed->value)
                        ->count(),
                ],
                'invoices_pending' => [
                    'total' => Invoice::where('payment_status', PaymentStatus::Pending->value)->count(),
                    'overdue' => Invoice::where('payment_status', PaymentStatus::Overdue->value)->count(),
                ],
            ],
            'documentAlerts' => $this->buildDocumentAlerts($today, $expiryThreshold),
        ]);
    }

    /**
     * @return array<int, array{kind: string, label: string, subject: string, due_date: string|null, days_remaining: int}>
     */
    private function buildDocumentAlerts(Carbon $today, Carbon $expiryThreshold): array
    {
        $vehicleAlerts = Vehicle::query()
            ->select(['id', 'plate', 'internal_code', 'soat_due_date', 'rtm_due_date', 'operation_card_due_date'])
            ->where(function ($query) use ($expiryThreshold) {
                $query->whereNotNull('soat_due_date')->where('soat_due_date', '<=', $expiryThreshold)
                    ->orWhereNotNull('rtm_due_date')->where('rtm_due_date', '<=', $expiryThreshold)
                    ->orWhereNotNull('operation_card_due_date')->where('operation_card_due_date', '<=', $expiryThreshold);
            })
            ->get()
            ->flatMap(function (Vehicle $vehicle) use ($today, $expiryThreshold) {
                $fields = [
                    'soat_due_date' => 'SOAT',
                    'rtm_due_date' => 'RTM',
                    'operation_card_due_date' => 'Tarjeta de Operación',
                ];

                $rows = [];
                foreach ($fields as $column => $label) {
                    $dueDate = $vehicle->{$column};
                    if ($dueDate === null || $dueDate->greaterThan($expiryThreshold)) {
                        continue;
                    }
                    $rows[] = [
                        'kind' => 'vehicle',
                        'label' => $label,
                        'subject' => $vehicle->plate,
                        'due_date' => $dueDate->toDateString(),
                        'days_remaining' => (int) $today->diffInDays($dueDate, false),
                    ];
                }

                return $rows;
            });

        $driverAlerts = Driver::query()
            ->select(['id', 'first_name', 'first_lastname', 'license_due_date'])
            ->whereNotNull('license_due_date')
            ->where('license_due_date', '<=', $expiryThreshold)
            ->get()
            ->map(function (Driver $driver) use ($today) {
                return [
                    'kind' => 'driver',
                    'label' => 'Licencia',
                    'subject' => trim($driver->first_name.' '.$driver->first_lastname),
                    'due_date' => $driver->license_due_date?->toDateString(),
                    'days_remaining' => (int) $today->diffInDays($driver->license_due_date, false),
                ];
            });

        return $vehicleAlerts
            ->concat($driverAlerts)
            ->sortBy('days_remaining')
            ->values()
            ->take(self::ALERTS_MAX_ROWS)
            ->all();
    }
}
