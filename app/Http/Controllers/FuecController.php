<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Requests\FuecStoreRequest;
use App\Http\Requests\FuecUpdateRequest;
use App\Models\Fuec;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\QueryBuilder;

class FuecController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::VIEW_FUEC->value);
        $fuecs = QueryBuilder::for(Fuec::class)
            ->allowedFilters([])
            ->allowedSorts([])
            ->get();

        return Inertia::render('fuecs/index', [
            'fuecs' => $fuecs,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('fuecs/create');
    }

    public function store(FuecStoreRequest $request): RedirectResponse
    {
        $fuec = Fuec::create($request->validated());

        return redirect()->route('fuecs.index');
    }

    public function show(Request $request, Fuec $fuec): Response
    {
        Gate::authorize(Permission::VIEW_FUEC->value);

        return Inertia::render('fuecs/show', [
            'fuec' => $fuec,
        ]);
    }

    public function edit(Request $request, Fuec $fuec): Response
    {
        return Inertia::render('fuecs/edit', [
            'fuec' => $fuec,
        ]);
    }

    public function update(FuecUpdateRequest $request, Fuec $fuec): RedirectResponse
    {
        $fuec->update($request->validated());

        return redirect()->route('fuecs.index');
    }

    public function destroy(Request $request, Fuec $fuec): RedirectResponse
    {
        $fuec->delete();

        return redirect()->route('fuecs.index');
    }
}
