<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\Role as RoleEnum;
use App\Http\Requests\RoleUpdateRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(): Response
    {
        Gate::authorize(Permission::VIEW_USERS->value);

        $roles = Role::query()
            ->withCount(['users', 'permissions'])
            ->orderByRaw($this->roleOrderRawSql())
            ->get();

        return Inertia::render('roles/index', [
            'roles' => $roles->map(fn (Role $role) => $this->serializeCard($role))->values()->all(),
        ]);
    }

    public function show(Role $role): Response
    {
        Gate::authorize(Permission::VIEW_USERS->value);

        $role->loadMissing('permissions:id,name');
        $role->loadCount('users');

        $users = User::role($role->name)
            ->orderBy('name')
            ->limit(6)
            ->get(['id', 'name', 'email']);

        $assignedPermissions = $role->permissions->pluck('name')->all();

        return Inertia::render('roles/show', [
            'role' => $this->serializeDetail($role),
            'users' => $users->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ])->values()->all(),
            'permissionGroups' => Permission::groupedForUi(),
            'assignedPermissions' => $assignedPermissions,
        ]);
    }

    public function update(RoleUpdateRequest $request, Role $role): RedirectResponse
    {
        $data = $request->validated();

        $oldDescription = $role->description;
        $oldPermissions = $role->permissions->pluck('name')->all();
        sort($oldPermissions);

        $role->forceFill(['description' => $data['description'] ?? null])->save();
        $role->syncPermissions($data['permissions']);

        $newPermissions = $role->fresh()->permissions->pluck('name')->all();
        sort($newPermissions);

        $added = array_values(array_diff($newPermissions, $oldPermissions));
        $removed = array_values(array_diff($oldPermissions, $newPermissions));

        if ($added !== [] || $removed !== []) {
            activity()
                ->performedOn($role)
                ->causedBy($request->user())
                ->withProperties([
                    'added' => $added,
                    'removed' => $removed,
                ])
                ->event('permissions_synced')
                ->log('permissions_synced');
        }

        // The model trait covers description changes, but we can leave a
        // breadcrumb here too for combined updates. The trait already handles it.
        unset($oldDescription);

        return redirect()->route('roles.show', ['role' => $role->name])
            ->with('success', 'Rol actualizado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCard(Role $role): array
    {
        $enum = RoleEnum::tryFrom($role->name);

        return [
            'id' => $role->id,
            'name' => $role->name,
            'label' => $enum?->label() ?? $role->name,
            'description' => $role->description,
            'users_count' => $role->users_count,
            'permissions_count' => $role->permissions_count,
            'locked' => $role->name === RoleEnum::SUPER_ADMIN->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDetail(Role $role): array
    {
        $enum = RoleEnum::tryFrom($role->name);

        return [
            'id' => $role->id,
            'name' => $role->name,
            'label' => $enum?->label() ?? $role->name,
            'description' => $role->description,
            'users_count' => $role->users_count,
            'locked' => $role->name === RoleEnum::SUPER_ADMIN->value,
        ];
    }

    private function roleOrderRawSql(): string
    {
        $cases = [];
        $i = 0;
        foreach (RoleEnum::cases() as $r) {
            $cases[] = sprintf("WHEN '%s' THEN %d", $r->value, $i++);
        }

        return 'CASE name '.implode(' ', $cases).' ELSE 99 END';
    }
}
