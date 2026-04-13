<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::VIEW_USERS->value);

        $users = QueryBuilder::for(User::class)
            ->with('roles:id,name')
            ->allowedFilters([
                'name',
                'email',
                AllowedFilter::callback('role', fn ($query, $value) => $query->whereHas('roles', fn ($q) => $q->where('name', $value))),
            ])
            ->allowedSorts(['name', 'email', 'created_at'])
            ->defaultSort('name')
            ->get();

        return Inertia::render('users/index', [
            'users' => $users,
            'availableRoles' => $this->availableRoles(),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize(Permission::CREATE_USERS->value);

        return Inertia::render('users/create', [
            'availableRoles' => $this->availableRoles(),
        ]);
    }

    public function store(UserStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'email_verified_at' => now(),
        ]);

        $user->syncRoles([$data['role']]);

        return redirect()->route('users.index');
    }

    public function show(Request $request, User $user): Response
    {
        Gate::authorize(Permission::VIEW_USERS->value);

        $user->load('roles:id,name');

        return Inertia::render('users/show', [
            'user' => $user,
        ]);
    }

    public function edit(Request $request, User $user): Response
    {
        Gate::authorize(Permission::UPDATE_USERS->value);

        $user->load('roles:id,name');

        return Inertia::render('users/edit', [
            'user' => $user,
            'availableRoles' => $this->availableRoles(),
        ]);
    }

    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        $user->syncRoles([$data['role']]);

        return redirect()->route('users.index');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize(Permission::DELETE_USERS->value);

        if ($request->user()?->id === $user->id) {
            return redirect()->route('users.index')->withErrors([
                'user' => 'No puedes eliminar tu propia cuenta.',
            ]);
        }

        $user->delete();

        return redirect()->route('users.index');
    }

    /**
     * Roles an admin can assign from this screen. Super admin is excluded
     * because it is bootstrapped via the SUPER_ADMIN_USER env + bypasses
     * all gates — not something we expose through a form.
     *
     * @return list<array{value: string, label: string}>
     */
    private function availableRoles(): array
    {
        return [
            ['value' => Role::ADMIN->value, 'label' => 'Administrador'],
            ['value' => Role::OPERATOR->value, 'label' => 'Operador'],
            ['value' => Role::DRIVER->value, 'label' => 'Conductor'],
            ['value' => Role::ACCOUNTING->value, 'label' => 'Contabilidad'],
        ];
    }
}
