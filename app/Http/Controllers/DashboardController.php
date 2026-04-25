<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\ServiceStatus;
use App\Enums\VehicleStatus;
use App\Models\Contract;
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
     * Days-ahead window used to flag "por vencer" vehicle + driver
     * documents (SOAT, RTM, operation card, driver license).
     */
    private const EXPIRY_ALERT_DAYS = 30;

    /**
     * Days-ahead window used to flag contracts as "por vencer".
     * Contracts have a longer renewal lead time than SOAT/RTM/license,
     * so we surface them earlier. Mirrors CONTRACT_EXPIRY_WINDOW_DAYS
     * in resources/js/lib/document-status.ts.
     */
    private const CONTRACT_EXPIRY_ALERT_DAYS = 60;

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

        $operationTz = (string) config('app.operation_tz', 'America/Bogota');
        $today = Carbon::now($operationTz)->startOfDay();
        $expiryThreshold = $today->copy()->addDays(self::EXPIRY_ALERT_DAYS);
        $contractExpiryThreshold = $today->copy()->addDays(self::CONTRACT_EXPIRY_ALERT_DAYS);
        $todayString = $today->toDateString();

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
                    'total' => Service::whereDate('service_date_local', $todayString)->count(),
                    'open' => Service::whereDate('service_date_local', $todayString)
                        ->where('service_status', ServiceStatus::Open->value)
                        ->count(),
                    'closed' => Service::whereDate('service_date_local', $todayString)
                        ->where('service_status', ServiceStatus::Closed->value)
                        ->count(),
                ],
                'invoices_pending' => [
                    'total' => Invoice::where('payment_status', PaymentStatus::Pending->value)->count(),
                    'overdue' => Invoice::where('payment_status', PaymentStatus::Overdue->value)->count(),
                ],
            ],
            'documentAlerts' => $this->buildDocumentAlerts($today, $expiryThreshold, $contractExpiryThreshold),
        ]);
    }

    /**
     * @return array<int, array{kind: 'vehicle'|'driver'|'contract', label: string, subject: string, due_date: string|null, days_remaining: int, link: string}>
     */
    private function buildDocumentAlerts(Carbon $today, Carbon $expiryThreshold, Carbon $contractExpiryThreshold): array
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
                    $daysRemaining = (int) $today->diffInDays($dueDate, false);
                    $rows[] = [
                        'kind' => 'vehicle',
                        'label' => $label,
                        'subject' => $vehicle->plate,
                        'due_date' => $dueDate->toDateString(),
                        'days_remaining' => $daysRemaining,
                        'link' => $this->vehicleAlertLink($daysRemaining),
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
                $daysRemaining = (int) $today->diffInDays($driver->license_due_date, false);

                return [
                    'kind' => 'driver',
                    'label' => 'Licencia',
                    'subject' => trim($driver->first_name.' '.$driver->first_lastname),
                    'due_date' => $driver->license_due_date?->toDateString(),
                    'days_remaining' => $daysRemaining,
                    'link' => $this->driverAlertLink($daysRemaining),
                ];
            });

        $contractAlerts = Contract::query()
            ->select(['id', 'contract_number', 'end_date', 'active'])
            ->where('active', true)
            ->whereNotNull('end_date')
            ->where('end_date', '<=', $contractExpiryThreshold)
            ->get()
            ->map(function (Contract $contract) use ($today) {
                $daysRemaining = (int) $today->diffInDays($contract->end_date, false);

                return [
                    'kind' => 'contract',
                    'label' => 'Contrato',
                    'subject' => $contract->contract_number,
                    'due_date' => $contract->end_date?->toDateString(),
                    'days_remaining' => $daysRemaining,
                    'link' => $this->contractAlertLink($daysRemaining),
                ];
            });

        return $vehicleAlerts
            ->concat($driverAlerts)
            ->concat($contractAlerts)
            ->sortBy('days_remaining')
            ->values()
            ->take(self::ALERTS_MAX_ROWS)
            ->all();
    }

    /**
     * Build the deep-link a vehicle alert should navigate to. Already-expired
     * documents jump to the vehicles index filtered by docs_status=expired;
     * documents within the 30-day window jump to docs_status=expiring_soon.
     */
    private function vehicleAlertLink(int $daysRemaining): string
    {
        return $daysRemaining < 0
            ? '/vehicles?filter[docs_status]=expired'
            : '/vehicles?filter[docs_status]=expiring_soon';
    }

    /**
     * Build the deep-link a driver alert should navigate to. Symmetric
     * with vehicleAlertLink — already-expired licenses jump to the
     * drivers index filtered by license_status=expired; licenses within
     * the 30-day window jump to license_status=expiring_soon.
     */
    private function driverAlertLink(int $daysRemaining): string
    {
        return $daysRemaining < 0
            ? '/drivers?filter[license_status]=expired'
            : '/drivers?filter[license_status]=expiring_soon';
    }

    /**
     * Build the deep-link a contract alert should navigate to. Symmetric
     * with vehicleAlertLink / driverAlertLink — already-expired contracts
     * jump to the contracts index filtered by contract_status=vencido;
     * contracts within the 60-day window jump to expiring_soon. The
     * backend accepts both `vencido`/`expired` and
     * `por_vencer`/`expiring_soon` as aliases for the same bucket.
     */
    private function contractAlertLink(int $daysRemaining): string
    {
        return $daysRemaining < 0
            ? '/contracts?filter[contract_status]=vencido'
            : '/contracts?filter[contract_status]=expiring_soon';
    }
}
