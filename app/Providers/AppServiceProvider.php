<?php

namespace App\Providers;

use App\Enums\Role;
use App\Listeners\UpdateLastLoginAt;
use App\Models\Service;
use App\Observers\ServiceObserver;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Blueprint custom generators are registered in config/blueprint.php.
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureMacros();

        Service::observe(ServiceObserver::class);

        Event::listen(Login::class, UpdateLastLoginAt::class);

        // Super Admin User can bypass all authorization checks
        Gate::before(function ($user, $ability) {
            return $user->hasRole(Role::SUPER_ADMIN) ? true : null;
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureMacros(): void
    {
        Request::macro('perPage', fn (?int $default = null): int => min(
            $this->integer('per_page', $default ?? config('app.per_page', 10)),
            config('app.per_page_max', 100),
        ));
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(
            fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}
