<?php

namespace App\Http\Controllers;

use App\Enums\DayStatusEnum;
use App\Enums\Permission;
use App\Enums\ServiceStatus;
use App\Http\Requests\DayStatusStoreRequest;
use App\Http\Requests\DayStatusUpdateRequest;
use App\Models\DayStatus;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DayStatusController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::VIEW_DAY_SUMMARY->value);

        $request->validate([
            'year' => ['sometimes', 'integer', 'between:2020,2099'],
        ]);

        $year = (int) $request->input('year', now()->year);

        $dayStatuses = DayStatus::whereYear('date', $year)
            ->with('executor:id,name')
            ->get()
            ->keyBy(fn (DayStatus $ds): string => $ds->date->format('Y-m-d'));

        $serviceCounts = Service::query()
            ->selectRaw("service_date, count(*) as total, sum(case when service_status = 'open' then 1 else 0 end) as open_count")
            ->whereYear('service_date', $year)
            ->whereNull('deleted_at')
            ->groupBy('service_date')
            ->get()
            ->keyBy(fn ($row): string => $row->service_date instanceof \DateTimeInterface ? $row->service_date->format('Y-m-d') : (string) $row->service_date);

        return Inertia::render('day-statuses/index', [
            'dayStatuses' => $dayStatuses,
            'serviceCounts' => $serviceCounts,
            'year' => $year,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('day-statuses/create');
    }

    public function store(DayStatusStoreRequest $request): RedirectResponse
    {
        $dayStatus = DayStatus::create($request->validated());

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
        return Inertia::render('day-statuses/edit', [
            'dayStatus' => $dayStatus,
        ]);
    }

    public function update(DayStatusUpdateRequest $request, DayStatus $dayStatus): RedirectResponse
    {
        $dayStatus->update($request->validated());

        return redirect()->route('day-statuses.index');
    }

    public function execute(Request $request, DayStatus $dayStatus): RedirectResponse
    {
        Gate::authorize(Permission::EXECUTE_DAY->value);

        $services = Service::whereDate('service_date', $dayStatus->date)
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

        return redirect()->back()->with('success', 'Día ejecutado correctamente.');
    }

    public function destroy(Request $request, DayStatus $dayStatus): RedirectResponse
    {
        $dayStatus->delete();

        return redirect()->route('day-statuses.index');
    }
}
