<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Requests\DayStatusStoreRequest;
use App\Http\Requests\DayStatusUpdateRequest;
use App\Models\DayStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class DayStatusController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::VIEW_DAY_SUMMARY->value);
        $dayStatuses = QueryBuilder::for(DayStatus::class)
            ->allowedFilters([
                AllowedFilter::exact('date'),
                AllowedFilter::exact('status'),
            ])
            ->allowedSorts(['date', 'status'])
            ->get();

        return Inertia::render('day-statuses/index', [
            'dayStatuses' => $dayStatuses,
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

    public function destroy(Request $request, DayStatus $dayStatus): RedirectResponse
    {
        $dayStatus->delete();

        return redirect()->route('day-statuses.index');
    }
}
