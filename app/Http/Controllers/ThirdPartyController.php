<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Requests\ThirdPartyStoreRequest;
use App\Http\Requests\ThirdPartyUpdateRequest;
use App\Models\ThirdParty;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\QueryBuilder;

class ThirdPartyController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::VIEW_THIRD_PARTIES->value);
        $thirdParties = QueryBuilder::for(ThirdParty::class)
            ->allowedFilters([])
            ->allowedSorts([])
            ->get();

        return Inertia::render('third-parties/index', [
            'thirdParties' => $thirdParties,
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize(Permission::CREATE_THIRD_PARTIES->value);

        return Inertia::render('third-parties/create');
    }

    public function store(ThirdPartyStoreRequest $request): RedirectResponse
    {
        Gate::authorize(Permission::CREATE_THIRD_PARTIES->value);
        $thirdParty = ThirdParty::create($request->validated());

        return redirect()->route('third-parties.index');
    }

    public function show(Request $request, ThirdParty $thirdParty): Response
    {
        Gate::authorize(Permission::VIEW_THIRD_PARTIES->value);

        return Inertia::render('third-parties/show', [
            'thirdParty' => $thirdParty,
        ]);
    }

    public function edit(Request $request, ThirdParty $thirdParty): Response
    {
        Gate::authorize(Permission::UPDATE_THIRD_PARTIES->value);

        return Inertia::render('third-parties/edit', [
            'thirdParty' => $thirdParty,
        ]);
    }

    public function update(ThirdPartyUpdateRequest $request, ThirdParty $thirdParty): RedirectResponse
    {
        Gate::authorize(Permission::UPDATE_THIRD_PARTIES->value);
        $thirdParty->update($request->validated());

        return redirect()->route('third-parties.index');
    }

    public function destroy(Request $request, ThirdParty $thirdParty): RedirectResponse
    {
        Gate::authorize(Permission::DELETE_THIRD_PARTIES->value);
        $thirdParty->delete();

        return redirect()->route('third-parties.index');
    }
}
