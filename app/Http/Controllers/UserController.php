<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\Role as RoleEnum;
use App\Http\Requests\UserResetPasswordRequest;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserToggleActiveRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Models\User;
use App\Notifications\WelcomeUserNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::VIEW_USERS->value);

        $users = QueryBuilder::for(User::class)
            ->with('roles:id,name')
            ->allowedFilters([
                AllowedFilter::callback('search', function (Builder $query, $value): void {
                    $query->where(function (Builder $q) use ($value): void {
                        $q->where('name', 'ilike', '%'.$value.'%')
                            ->orWhere('email', 'ilike', '%'.$value.'%');
                    });
                }),
                AllowedFilter::callback('roles', function (Builder $query, $value): void {
                    $values = is_array($value) ? $value : explode(',', (string) $value);
                    $values = array_filter(array_map('trim', $values));
                    if ($values === []) {
                        return;
                    }
                    $query->whereHas('roles', fn (Builder $q) => $q->whereIn('name', $values));
                }),
                AllowedFilter::callback('is_active', function (Builder $query, $value): void {
                    $query->where('is_active', filter_var($value, FILTER_VALIDATE_BOOL));
                }),
            ])
            ->allowedSorts([
                'name',
                'email',
                'last_login_at',
                'created_at',
                AllowedSort::field('is_active'),
            ])
            ->defaultSort('name')
            ->paginate($request->perPage(10))
            ->withQueryString();

        $payload = $users->toArray();
        $payload['data'] = array_map(
            fn (User $user) => $this->serializeUser($user),
            $users->items(),
        );

        return Inertia::render('users/index', [
            'users' => $payload,
            'availableRoles' => $this->availableRoles(),
            'filters' => [
                'search' => $request->input('filter.search'),
                'roles' => $this->normalizeRolesFilter($request->input('filter.roles')),
                'is_active' => $request->input('filter.is_active'),
            ],
        ]);
    }

    public function store(UserStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $sendWelcome = (bool) ($data['send_welcome_email'] ?? false);

        $temporaryPassword = $sendWelcome ? Str::password(16) : $data['password'];

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($temporaryPassword),
            'email_verified_at' => now(),
            'is_active' => $data['is_active'],
            'must_change_password' => $sendWelcome,
        ]);

        $user->syncRoles($data['roles']);

        if ($sendWelcome) {
            $user->notify(new WelcomeUserNotification);
        }

        return redirect()->route('users.index')->with('success', 'Usuario creado.');
    }

    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $oldRoles = $user->roles->pluck('name')->sort()->values()->all();

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'is_active' => $data['is_active'],
        ])->save();

        $user->syncRoles($data['roles']);

        $newRoles = $user->roles->pluck('name')->sort()->values()->all();

        if ($oldRoles !== $newRoles) {
            activity()
                ->performedOn($user)
                ->causedBy($request->user())
                ->withProperties([
                    'old_roles' => $oldRoles,
                    'new_roles' => $newRoles,
                ])
                ->event('roles_synced')
                ->log('roles_synced');
        }

        return redirect()->route('users.index')->with('success', 'Usuario actualizado.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize(Permission::DELETE_USERS->value);

        if ($user->hasRole(RoleEnum::SUPER_ADMIN->value)
            && ! $request->user()?->hasRole(RoleEnum::SUPER_ADMIN->value)
        ) {
            abort(403);
        }

        if ($request->user()?->id === $user->id) {
            throw ValidationException::withMessages([
                'user' => 'No puedes eliminar tu propia cuenta.',
            ]);
        }

        if ($user->hasRole(RoleEnum::ADMIN->value)
            && User::role(RoleEnum::ADMIN->value)->where('id', '!=', $user->id)->count() === 0
        ) {
            throw ValidationException::withMessages([
                'user' => 'No puedes eliminar al último administrador del sistema.',
            ]);
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'Usuario eliminado.');
    }

    public function toggleActive(UserToggleActiveRequest $request, User $user): RedirectResponse
    {
        $user->forceFill(['is_active' => ! $user->is_active])->save();

        $message = $user->is_active ? 'Usuario activado.' : 'Usuario desactivado.';

        return redirect()->back()->with('success', $message);
    }

    public function resetPassword(UserResetPasswordRequest $request, User $user): RedirectResponse
    {
        $user->forceFill([
            'password' => Hash::make(Str::password(16)),
            'must_change_password' => true,
        ])->save();

        Password::sendResetLink(['email' => $user->email]);

        return redirect()->back()->with('success', 'Se envió un enlace de restablecimiento al correo del usuario.');
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
            ['value' => RoleEnum::ADMIN->value, 'label' => RoleEnum::ADMIN->label()],
            ['value' => RoleEnum::OPERATOR->value, 'label' => RoleEnum::OPERATOR->label()],
            ['value' => RoleEnum::DRIVER->value, 'label' => RoleEnum::DRIVER->label()],
            ['value' => RoleEnum::ACCOUNTING->value, 'label' => RoleEnum::ACCOUNTING->label()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
            'roles' => $user->roles->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $this->roleLabel($role->name),
            ])->values()->all(),
        ];
    }

    private function roleLabel(string $name): string
    {
        return RoleEnum::tryFrom($name)?->label() ?? $name;
    }

    /**
     * @return list<string>
     */
    private function normalizeRolesFilter(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_array($value)) {
            return array_values(array_filter(array_map('strval', $value)));
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $value))));
    }
}
