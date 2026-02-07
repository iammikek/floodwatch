<?php

namespace App\Providers;

use App\Models\User;
use App\Services\FloodWatchPromptBuilder;
use Illuminate\Support\Facades\Gate;
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
        Gate::define('viewPulse', function (?User $user): bool {
            return $user !== null && $user->isAdmin();
        });

        Gate::define('accessAdmin', fn (User $user): bool => $user->isAdmin());
    }
}
