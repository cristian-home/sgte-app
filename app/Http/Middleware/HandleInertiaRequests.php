<?php

namespace App\Http\Middleware;

use App\Support\Tz;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'tagline' => config('app.tagline'),
            'url' => $request->fullUrl(),
            'auth' => [
                'user' => $request->user(),
                'permissions' => $request->user()?->getAllPermissions()->pluck('name')->toArray() ?? [],
                'roles' => $request->user()?->getRoleNames()->toArray() ?? [],
                'featureFlags' => [
                    'fuec' => (bool) config('sgte.fuec_enabled'),
                    'gps' => (bool) config('sgte.gps_enabled'),
                ],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'config' => [
                'operation_tz' => Tz::operation(),
                'viewer_tz' => Tz::viewer($request),
                'version' => config('app.version'),
                'environment' => config('app.env'),
            ],
            // Surface controller-set flash keys to the frontend. Used by
            // the cascade-create dialogs (service ← contract ← tercero)
            // so the parent picker can auto-select the newly-created
            // entity after a `back()->with('created_*_id', $id)`.
            'flash' => [
                'created_contract_id' => fn () => $request->session()->get('created_contract_id'),
                'created_third_party_id' => fn () => $request->session()->get('created_third_party_id'),
            ],
        ];
    }
}
