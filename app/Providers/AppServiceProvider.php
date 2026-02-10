<?php

namespace App\Providers;

use App\Models\User;
use App\Services\FloodWatchPromptBuilder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FloodWatchPromptBuilder::class, function () {
            return new FloodWatchPromptBuilder(
                config('flood-watch.prompt_version', 'v1')
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('flood-watch-api', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        Gate::define('viewPulse', function (?User $user): bool {
            return $user !== null && $user->isAdmin();
        });

        Gate::define('accessAdmin', fn (?User $user): bool => $user !== null && $user->isAdmin());
    }
}
