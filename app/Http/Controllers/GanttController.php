<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\VehicleStatus;
use App\Models\DayStatus;
use App\Models\Municipality;
use App\Models\Service;
use App\Models\Vehicle;
use App\Support\ServiceDocumentChecks;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GanttController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::VIEW_SERVICES->value);

        $request->validate([
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'municipality_id' => ['sometimes', 'integer', 'exists:municipalities,id'],
        ]);

        $date = $request->input('date', now()->toDateString());
        $municipalityId = $request->input('municipality_id');

        $vehicles = Vehicle::query()
            ->where('status', VehicleStatus::Active)
            ->when($municipalityId, fn ($q) => $q->where('municipality_id', $municipalityId))
            ->with([
                'thirdParty:id,company_name,first_name,first_lastname,is_natural_person',
                'municipality:id,name',
            ])
            ->select([
                'id', 'plate', 'internal_code', 'is_third_party', 'third_party_id',
                'municipality_id', 'soat_due_date', 'rtm_due_date', 'operation_card_due_date',
            ])
            ->orderBy('plate')
            ->get()
            ->map(function (Vehicle $vehicle) use ($date): array {
                // REQ-004: annotate vehicles whose legal documents have
                // expired on the service date. The frontend renders the
                // row disabled and the service form blocks creation
                // (ServiceStoreRequest::validateVehicleDocumentsNotExpired).
                $expiredDocuments = [];
                $documents = [
                    'soat_due_date' => 'SOAT',
                    'rtm_due_date' => 'RTM',
                    'operation_card_due_date' => 'Tarjeta de Operación',
                ];

                foreach ($documents as $column => $label) {
                    $dueDate = $vehicle->{$column};
                    if ($dueDate === null) {
                        $expiredDocuments[] = $label;

                        continue;
                    }
                    if ($dueDate->toDateString() < $date) {
                        $expiredDocuments[] = $label;
                    }
                }

                return [
                    ...$vehicle->toArray(),
                    'blocked' => $expiredDocuments !== [],
                    'expired_documents' => $expiredDocuments,
                ];
            });

        $serviceDateCarbon = Carbon::parse($date);

        $services = Service::query()
            ->whereDate('service_date', $date)
            ->whereIn('vehicle_id', $vehicles->pluck('id'))
            ->with([
                'vehicle:id,plate,type,is_third_party,soat_due_date,rtm_due_date,operation_card_due_date',
                'driver:id,first_name,first_lastname,license_category,license_due_date,has_social_security',
                'contract:id,contract_number,third_party_id',
                'contract.thirdParty:id,company_name,first_name,first_lastname,is_natural_person',
            ])
            ->get()
            ->map(function (Service $service) use ($serviceDateCarbon): array {
                // REQ-004 / REQ-005 render-time re-check: flag services
                // whose assigned vehicle or driver has paperwork expired
                // as-of service_date. Gantt greys the bar and shows the
                // reasons in the tooltip.
                $reasons = [];

                if ($service->vehicle) {
                    $reasons = array_merge(
                        $reasons,
                        ServiceDocumentChecks::vehicleDocumentsValid($service->vehicle, $serviceDateCarbon),
                    );
                }

                if ($service->driver && $service->vehicle) {
                    $reasons = array_merge(
                        $reasons,
                        ServiceDocumentChecks::driverLicenseValid($service->driver, $service->vehicle, $serviceDateCarbon),
                    );
                }

                return [
                    ...$service->toArray(),
                    'blocked' => $reasons !== [],
                    'blocked_reasons' => $reasons,
                ];
            });

        $dayStatus = DayStatus::whereDate('date', $date)
            ->with('executor:id,name')
            ->first();

        $municipalities = Municipality::query()
            ->with('department:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'department_id']);

        return Inertia::render('gantt/index', [
            'vehicles' => $vehicles,
            'services' => $services,
            'dayStatus' => $dayStatus,
            'municipalities' => $municipalities,
            'date' => $date,
            'municipalityId' => $municipalityId ? (int) $municipalityId : null,
            'canCreateServices' => Gate::allows(Permission::CREATE_SERVICES->value),
        ]);
    }
}
