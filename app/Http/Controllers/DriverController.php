<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Requests\DriverStoreRequest;
use App\Http\Requests\DriverUpdateRequest;
use App\Models\DocumentType;
use App\Models\Driver;
use App\Models\Eps;
use App\Models\Municipality;
use App\Models\PensionFund;
use App\Models\SeveranceFund;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class DriverController extends Controller
{
    /**
     * Days-ahead window used by the license_status filter to flag
     * "por vencer" licenses. Mirrors the dashboard threshold and the
     * matching constant in VehicleController.
     */
    private const LICENSE_EXPIRY_WINDOW_DAYS = 30;

    public function index(Request $request): Response
    {
        Gate::authorize(Permission::VIEW_DRIVERS->value);

        $drivers = QueryBuilder::for(Driver::class)
            ->with([
                'municipality:id,name,department_id',
                'municipality.department:id,name',
                'documentType:id,code',
                'user:id,name,email',
            ])
            ->allowedFilters([
                'identification_number',
                'first_name',
                'first_lastname',
                AllowedFilter::exact('municipality_id'),
                AllowedFilter::exact('license_category'),
                AllowedFilter::exact('active'),
                AllowedFilter::exact('has_social_security'),
                AllowedFilter::callback('license_status', function (Builder $query, $value) {
                    // Faceted filter UI is multi-select but license_status is
                    // semantically single-select. Honor the first comma-
                    // separated value (mirrors VehicleController docs_status).
                    $first = is_array($value) ? ($value[0] ?? '') : explode(',', (string) $value)[0];
                    $this->applyLicenseStatusFilter($query, $first);
                }),
            ])
            ->allowedSorts(['first_name', 'first_lastname', 'municipality_id', 'license_due_date', 'active'])
            ->defaultSort('first_lastname')
            ->paginate($request->perPage())
            ->withQueryString();

        return Inertia::render('drivers/index', [
            'drivers' => $drivers,
            'municipalities' => $this->municipalitiesPayload(),
            'documentTypes' => DocumentType::orderBy('code')->get(['id', 'code', 'name']),
            'eps' => Eps::orderBy('name')->get(['id', 'code', 'name']),
            'pensionFunds' => PensionFund::orderBy('name')->get(['id', 'code', 'name']),
            'severanceFunds' => SeveranceFund::orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    /**
     * Filter drivers by the aggregate state of their single legal
     * document (license_due_date).
     *
     * - expired       → license is null or strictly before today
     * - expiring_soon → license is within [today, today+30]
     * - ok            → license is more than 30 days out
     */
    private function applyLicenseStatusFilter(Builder $query, string $value): void
    {
        $today = Carbon::today()->toDateString();
        $threshold = Carbon::today()->addDays(self::LICENSE_EXPIRY_WINDOW_DAYS)->toDateString();

        match ($value) {
            'expired' => $query->where(function (Builder $q) use ($today): void {
                $q->whereNull('license_due_date')
                    ->orWhereDate('license_due_date', '<', $today);
            }),
            'expiring_soon' => $query
                ->whereNotNull('license_due_date')
                ->whereBetween('license_due_date', [$today, $threshold]),
            'ok' => $query
                ->whereNotNull('license_due_date')
                ->whereDate('license_due_date', '>', $threshold),
            default => null, // ignore unknown values
        };
    }

    /**
     * Shared municipality payload — eager-loads department for the
     * combobox grouping and sorts by name.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Municipality>
     */
    private function municipalitiesPayload(): \Illuminate\Database\Eloquent\Collection
    {
        return Municipality::query()
            ->with('department:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'department_id']);
    }

    public function create(Request $request): Response
    {
        Gate::authorize(Permission::CREATE_DRIVERS->value);

        return Inertia::render('drivers/create', [
            'municipalities' => $this->municipalitiesPayload(),
            'documentTypes' => DocumentType::orderBy('code')->get(['id', 'code', 'name']),
            'eps' => Eps::orderBy('name')->get(['id', 'code', 'name']),
            'pensionFunds' => PensionFund::orderBy('name')->get(['id', 'code', 'name']),
            'severanceFunds' => SeveranceFund::orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(DriverStoreRequest $request): RedirectResponse
    {
        Gate::authorize(Permission::CREATE_DRIVERS->value);
        $driver = Driver::create($request->validated());

        return redirect()->route('drivers.index');
    }

    public function show(Request $request, Driver $driver): Response
    {
        Gate::authorize(Permission::VIEW_DRIVERS->value);

        return Inertia::render('drivers/show', [
            'driver' => $driver,
        ]);
    }

    public function edit(Request $request, Driver $driver): Response
    {
        Gate::authorize(Permission::UPDATE_DRIVERS->value);

        return Inertia::render('drivers/edit', [
            'driver' => $driver,
            'municipalities' => $this->municipalitiesPayload(),
            'documentTypes' => DocumentType::orderBy('code')->get(['id', 'code', 'name']),
            'eps' => Eps::orderBy('name')->get(['id', 'code', 'name']),
            'pensionFunds' => PensionFund::orderBy('name')->get(['id', 'code', 'name']),
            'severanceFunds' => SeveranceFund::orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function update(DriverUpdateRequest $request, Driver $driver): RedirectResponse
    {
        Gate::authorize(Permission::UPDATE_DRIVERS->value);
        $driver->update($request->validated());

        return redirect()->route('drivers.index');
    }

    public function destroy(Request $request, Driver $driver): RedirectResponse
    {
        Gate::authorize(Permission::DELETE_DRIVERS->value);
        $driver->delete();

        return redirect()->route('drivers.index');
    }
}
