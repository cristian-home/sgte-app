<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Requests\ContractStoreRequest;
use App\Http\Requests\ContractUpdateRequest;
use App\Models\Contract;
use App\Models\Service;
use App\Models\ThirdParty;
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ContractController extends Controller
{
    /**
     * Days-ahead window used by the `por_vencer` bucket of the
     * contract_status filter. Mirrors `CONTRACT_EXPIRY_ALERT_DAYS`
     * in DashboardController and `CONTRACT_EXPIRY_WINDOW_DAYS` in
     * `resources/js/lib/document-status.ts`.
     */
    private const CONTRACT_EXPIRY_WINDOW_DAYS = 60;

    public function index(Request $request): Response|JsonResponse
    {
        Gate::authorize(Permission::VIEW_CONTRACTS->value);

        $contracts = QueryBuilder::for(Contract::class)
            ->with([
                'thirdParty:id,document_type_id,identification_number,is_natural_person,first_name,first_lastname,company_name,is_customer,is_provider',
                'thirdParty.documentType:id,code,name',
            ])
            ->allowedFilters([
                'contract_number',
                AllowedFilter::exact('contract_object'),
                AllowedFilter::exact('is_generic'),
                AllowedFilter::exact('active'),
                AllowedFilter::exact('third_party_id'),
                AllowedFilter::callback('contract_status', function (Builder $query, $value) {
                    $first = is_array($value) ? ($value[0] ?? '') : explode(',', (string) $value)[0];
                    $this->applyContractStatusFilter($query, $first);
                }),
            ])
            ->allowedSorts(['contract_number', 'start_at', 'end_at', 'created_at'])
            ->defaultSort('-created_at')
            ->paginate($request->perPage())
            ->withQueryString();

        if ($request->wantsJson()) {
            return response()->json($contracts);
        }

        return Inertia::render('contracts/index', [
            'contracts' => $contracts,
            'thirdParties' => $this->customerOptions(),
        ]);
    }

    /**
     * Filter contracts by the four-state temporal model
     * (vigente / por_vencer / vencido / inactivo). The dashboard
     * deep-links use the aliased `expiring_soon` / `expired` vocabulary;
     * both forms MUST resolve to the same bucket.
     */
    private function applyContractStatusFilter(Builder $query, string $value): void
    {
        $now = CarbonImmutable::now(Tz::operation());
        $thresholdInstant = $now->addDays(self::CONTRACT_EXPIRY_WINDOW_DAYS)->utc();
        $nowInstant = $now->utc();

        $normalized = match ($value) {
            'expiring_soon' => 'por_vencer',
            'expired' => 'vencido',
            default => $value,
        };

        match ($normalized) {
            'inactivo' => $query->where('active', false),
            'vencido' => $query
                ->where('active', true)
                ->where(function (Builder $q) use ($nowInstant): void {
                    $q->whereNull('end_at')->orWhere('end_at', '<=', $nowInstant);
                }),
            'por_vencer' => $query
                ->where('active', true)
                ->whereNotNull('end_at')
                ->where('end_at', '>', $nowInstant)
                ->where('end_at', '<=', $thresholdInstant),
            'vigente' => $query
                ->where('active', true)
                ->whereNotNull('end_at')
                ->where('end_at', '>', $thresholdInstant),
            default => null,
        };
    }

    /**
     * Build the customer option list used by the create modal, the
     * above-the-table combobox filter, and the create/edit standalone
     * pages. Returns only `is_customer = true` third parties with the
     * minimum fields the `<ThirdPartyCombobox />` needs.
     */
    private function customerOptions(): \Illuminate\Database\Eloquent\Collection
    {
        return ThirdParty::query()
            ->where('is_customer', true)
            ->with('documentType:id,code,name')
            ->orderBy('company_name')
            ->orderBy('first_lastname')
            ->get([
                'id',
                'document_type_id',
                'identification_number',
                'is_natural_person',
                'first_name',
                'first_lastname',
                'company_name',
                'is_customer',
                'is_provider',
            ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize(Permission::CREATE_CONTRACTS->value);

        return Inertia::render('contracts/create', [
            'thirdParties' => $this->customerOptions(),
        ]);
    }

    public function store(ContractStoreRequest $request): RedirectResponse
    {
        Gate::authorize(Permission::CREATE_CONTRACTS->value);

        $data = $request->validated();

        if ($request->boolean('is_generic') && empty($data['contract_number'])) {
            $year = now()->year;
            $sequence = Contract::query()
                ->where('contract_number', 'like', "GEN-%-{$year}")
                ->count() + 1;
            $data['contract_number'] = sprintf('GEN-%04d-%d', $sequence, $year);
        }

        Contract::create($data);

        return redirect()->route('contracts.index');
    }

    public function show(Request $request, Contract $contract): Response
    {
        Gate::authorize(Permission::VIEW_CONTRACTS->value);

        $contract->load([
            'thirdParty:id,document_type_id,identification_number,is_natural_person,first_name,first_lastname,company_name,is_customer,is_provider',
            'thirdParty.documentType:id,code,name',
        ]);

        $recentServices = Service::query()
            ->where('contract_id', $contract->id)
            ->with([
                'vehicle:id,plate',
                'driver:id,first_name,first_lastname',
            ])
            ->orderByDesc('service_date_local')
            ->orderByDesc('planned_start_at')
            ->limit(5)
            ->get(['id', 'service_date_local', 'planned_start_at', 'timezone', 'service_status', 'vehicle_id', 'driver_id', 'contract_id']);

        return Inertia::render('contracts/show', [
            'contract' => $contract,
            'recentServices' => $recentServices,
        ]);
    }

    public function edit(Request $request, Contract $contract): Response
    {
        Gate::authorize(Permission::UPDATE_CONTRACTS->value);

        $contract->load('thirdParty.documentType');

        return Inertia::render('contracts/edit', [
            'contract' => $contract,
            'thirdParties' => $this->customerOptions(),
        ]);
    }

    public function update(ContractUpdateRequest $request, Contract $contract): RedirectResponse
    {
        Gate::authorize(Permission::UPDATE_CONTRACTS->value);
        $contract->update($request->validated());

        return redirect()->route('contracts.index');
    }

    public function destroy(Request $request, Contract $contract): RedirectResponse
    {
        Gate::authorize(Permission::DELETE_CONTRACTS->value);
        $contract->delete();

        return redirect()->route('contracts.index');
    }
}
