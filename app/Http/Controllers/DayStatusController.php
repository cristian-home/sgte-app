<?php

namespace App\Http\Controllers;

use App\Enums\DayStatusEnum;
use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\ServiceStatus;
use App\Http\Requests\DayStatusStoreRequest;
use App\Http\Requests\DayStatusUpdateRequest;
use App\Models\DayStatus;
use App\Models\Service;
use App\Models\User;
use App\Notifications\DayExecutedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class DayStatusController extends Controller
{
    public function index(): RedirectResponse
    {
        $operationTz = (string) config('app.operation_tz', 'America/Bogota');

        return redirect()->route('day-statuses.calendar', ['year' => Carbon::now($operationTz)->year]);
    }

    public function calendar(int $year): Response
    {
        Gate::authorize(Permission::VIEW_DAY_SUMMARY->value);

        return Inertia::render('day-statuses/index', [
            ...$this->calendarData($year),
            'month' => null,
            'selectedDate' => null,
            'dayServices' => null,
        ]);
    }

    public function calendarMonth(Request $request, int $year, int $month): Response
    {
        Gate::authorize(Permission::VIEW_DAY_SUMMARY->value);

        $selectedDay = $request->query('selectedDay');
        $selectedDate = null;
        $dayServices = null;

        if ($selectedDay) {
            $selectedDate = sprintf('%d-%02d-%02d', $year, $month, (int) $selectedDay);
            $dayServices = Service::query()
                ->with(['contract:id,contract_number', 'vehicle:id,plate', 'driver:id,first_name,first_lastname'])
                ->whereDate('service_date_local', $selectedDate)
                ->orderBy('planned_start_at')
                ->get();
        }

        return Inertia::render('day-statuses/index', [
            ...$this->calendarData($year),
            'month' => $month,
            'selectedDate' => $selectedDate,
            'dayServices' => $dayServices,
        ]);
    }

    private function calendarData(int $year): array
    {
        $dayStatuses = DayStatus::whereYear('date', $year)
            ->with('executor:id,name')
            ->get()
            ->keyBy(fn (DayStatus $ds): string => $ds->date->format('Y-m-d'));

        $serviceCounts = Service::query()
            ->selectRaw("service_date_local, count(*) as total, sum(case when service_status = 'open' then 1 else 0 end) as open_count")
            ->whereYear('service_date_local', $year)
            ->whereNull('deleted_at')
            ->groupBy('service_date_local')
            ->get()
            ->keyBy(fn ($row): string => $row->service_date_local instanceof \DateTimeInterface ? $row->service_date_local->format('Y-m-d') : (string) $row->service_date_local);

        return [
            'dayStatuses' => $dayStatuses,
            'serviceCounts' => $serviceCounts,
            'year' => $year,
        ];
    }

    public function create(Request $request): Response
    {
        Gate::authorize(Permission::EXECUTE_DAY->value);

        return Inertia::render('day-statuses/create');
    }

    public function store(DayStatusStoreRequest $request): RedirectResponse
    {
        Gate::authorize(Permission::EXECUTE_DAY->value);

        DayStatus::create($request->validated());

        return redirect()->route('day-statuses.index');
    }

    public function show(Request $request, DayStatus $dayStatus): Response
    {
        Gate::authorize(Permission::VIEW_DAY_SUMMARY->value);

        return Inertia::render('day-statuses/show', [
            'dayStatus' => $dayStatus,
        ]);
    }

    public function edit(Request $request, DayStatus $dayStatus): Response
    {
        Gate::authorize(Permission::EXECUTE_DAY->value);

        return Inertia::render('day-statuses/edit', [
            'dayStatus' => $dayStatus,
        ]);
    }

    public function update(DayStatusUpdateRequest $request, DayStatus $dayStatus): RedirectResponse
    {
        Gate::authorize(Permission::EXECUTE_DAY->value);

        $previousStatus = $dayStatus->status instanceof DayStatusEnum
            ? $dayStatus->status
            : DayStatusEnum::tryFrom((string) $dayStatus->status);
        $data = $request->validated();
        $isSaReversal = $previousStatus === DayStatusEnum::Executed
            && ($data['status'] ?? null) === DayStatusEnum::Projected->value;

        if ($isSaReversal) {
            // Q3 / bug-log:BUG-05 — clear the executor metadata on reversal
            // so the row's state is consistent with its new projected status.
            $data['executor_id'] = null;
            $data['executed_at'] = null;
        }

        $justification = $data['justification'] ?? null;
        unset($data['justification']);

        $dayStatus->update($data);

        if ($isSaReversal) {
            activity()
                ->performedOn($dayStatus)
                ->causedBy($request->user())
                ->withProperties([
                    'reverted_from_executed' => true,
                    'justification' => $justification,
                ])
                ->log('Día revertido a proyectado');
        }

        return redirect()->route('day-statuses.index');
    }

    public function execute(Request $request, DayStatus $dayStatus): RedirectResponse
    {
        Gate::authorize(Permission::EXECUTE_DAY->value);

        $services = Service::whereDate('service_date_local', $dayStatus->date)
            ->whereNull('deleted_at')
            ->get();

        if ($services->isEmpty()) {
            return redirect()->back()->with('error', 'No se puede ejecutar un día sin servicios.');
        }

        $hasOpenServices = $services->contains(fn (Service $s) => $s->service_status !== ServiceStatus::Closed);

        if ($hasOpenServices) {
            return redirect()->back()->with('error', 'No se puede ejecutar el día. Existen servicios abiertos.');
        }

        $dayStatus->update([
            'status' => DayStatusEnum::Executed,
            'executor_id' => auth()->id(),
            'executed_at' => now(),
        ]);

        $accountingUsers = User::role(Role::ACCOUNTING->value)->get();
        if ($accountingUsers->isNotEmpty()) {
            Notification::send($accountingUsers, new DayExecutedNotification($dayStatus));
        }

        return redirect()->back()->with('success', 'Día ejecutado correctamente.');
    }

    public function destroy(Request $request, DayStatus $dayStatus): RedirectResponse
    {
        Gate::authorize(Permission::EXECUTE_DAY->value);

        $dayStatus->delete();

        return redirect()->route('day-statuses.index');
    }
}
