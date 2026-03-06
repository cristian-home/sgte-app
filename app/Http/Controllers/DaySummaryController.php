<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\ServiceStatus;
use App\Models\DayStatus;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DaySummaryController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::VIEW_DAY_SUMMARY->value);

        $request->validate([
            'date' => ['sometimes', 'date_format:Y-m-d'],
        ]);

        $date = $request->query('date', now()->format('Y-m-d'));

        $services = Service::where('service_date', $date)
            ->whereNull('deleted_at')
            ->with([
                'vehicle:id,plate,is_third_party,third_party_id',
                'vehicle.thirdParty:id,company_name,first_name,first_lastname,is_natural_person',
                'driver:id,first_name,first_lastname',
                'contract:id,contract_number,third_party_id',
                'contract.thirdParty:id,company_name,first_name,first_lastname,is_natural_person',
            ])
            ->withCount('serviceIncidents')
            ->orderBy('planned_start_time')
            ->get();

        $dayStatus = DayStatus::where('date', $date)->with('executor:id,name')->first();

        $summary = [
            'total' => $services->count(),
            'closed' => $services->where('service_status', ServiceStatus::Closed)->count(),
            'open' => $services->where('service_status', ServiceStatus::Open)->count(),
            'with_incidents' => $services->where('service_incidents_count', '>', 0)->count(),
            'third_party' => $services->filter(fn ($s) => $s->vehicle?->is_third_party)->count(),
        ];

        return Inertia::render('day-summary/index', [
            'services' => $services,
            'dayStatus' => $dayStatus,
            'summary' => $summary,
            'date' => $date,
            'canExecuteDay' => Gate::allows(Permission::EXECUTE_DAY->value),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize(Permission::VIEW_DAY_SUMMARY->value);

        $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $date = $request->query('date');

        $services = Service::where('service_date', $date)
            ->whereNull('deleted_at')
            ->with([
                'vehicle:id,plate,is_third_party,third_party_id',
                'vehicle.thirdParty:id,company_name,first_name,first_lastname,is_natural_person',
                'driver:id,first_name,first_lastname',
                'contract:id,contract_number,third_party_id',
                'contract.thirdParty:id,company_name,first_name,first_lastname,is_natural_person',
            ])
            ->withCount('serviceIncidents')
            ->orderBy('planned_start_time')
            ->get();

        return response()->streamDownload(function () use ($services) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['Placa', 'Conductor/Proveedor', 'Hora Inicio', 'Hora Fin', 'Duración (min)', 'Cliente', 'Estado', 'Novedades', 'Valor Unitario', 'Cantidad', 'Forma de Pago', 'Grupo Facturación']);
            foreach ($services as $service) {
                $driverOrProvider = $service->vehicle?->is_third_party
                    ? ($service->vehicle?->thirdParty?->company_name ?? $service->vehicle?->thirdParty?->first_name.' '.$service->vehicle?->thirdParty?->first_lastname)
                    : ($service->driver?->first_name.' '.$service->driver?->first_lastname);

                $client = $service->contract?->thirdParty?->company_name
                    ?? ($service->contract?->thirdParty?->first_name.' '.$service->contract?->thirdParty?->first_lastname);

                fputcsv($handle, [
                    $service->vehicle?->plate ?? '',
                    $driverOrProvider ?? '',
                    $service->planned_start_time,
                    $service->actual_end_time ?? '',
                    $service->planned_duration,
                    $client ?? '',
                    $service->service_status->value === 'closed' ? 'Cerrado' : 'Abierto',
                    $service->service_incidents_count,
                    $service->unit_value,
                    $service->quantity,
                    $service->payment_method->value,
                    $service->billing_group ?? '',
                ]);
            }
            fclose($handle);
        }, "resumen-dia-{$date}.csv", ['Content-Type' => 'text/csv']);
    }
}
