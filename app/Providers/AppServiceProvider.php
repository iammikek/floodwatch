<?php

namespace App\Providers;

use App\Services\FloodWatchPromptBuilder;
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
        //
    }
}
