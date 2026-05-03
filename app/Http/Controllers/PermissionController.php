<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PermissionController extends Controller
{
    public function index(): Response
    {
        Gate::authorize(Permission::VIEW_USERS->value);

        return Inertia::render('permissions/index', [
            'groups' => Permission::groupedForUi(),
        ]);
    }
}
