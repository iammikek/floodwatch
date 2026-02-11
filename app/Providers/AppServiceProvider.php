<?php

namespace App\Providers;

use App\Models\User;
use App\Services\FloodWatchPromptBuilder;
use App\Services\Tooling\Handlers\GetFloodDataHandler;
use App\Services\Tooling\Handlers\GetHighwaysIncidentsHandler;
use App\Support\Tooling\ToolRegistry;
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
        // Tag handlers for registry discovery
        $this->app->bind(GetFloodDataHandler::class);
        $this->app->bind(GetHighwaysIncidentsHandler::class);
        $this->app->tag([
            GetFloodDataHandler::class,
            GetHighwaysIncidentsHandler::class,
        ], 'llm.tool');

        // Registry from tagged handlers
        $this->app->singleton(ToolRegistry::class, function ($app) {
            return new ToolRegistry($app->tagged('llm.tool'));
        });

        // Prompt builder pulls tool definitions from the registry (DRY)
        $this->app->singleton(FloodWatchPromptBuilder::class, function ($app) {
            return new FloodWatchPromptBuilder(
                config('flood-watch.prompt_version', 'v1'),
                $app->make(ToolRegistry::class)
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
