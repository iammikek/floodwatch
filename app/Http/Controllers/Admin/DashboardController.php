<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HealthController;
use App\Models\LlmRequest;
use App\Models\User;
use App\Models\UserSearch;
use App\Services\OpenAiUsageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('accessAdmin');

        $cacheKey = config('flood-watch.cache_key_prefix', 'flood-watch').':admin:health:checks';
        $cacheTtl = config('flood-watch.health_check_cache_ttl', 60);

        $checks = Cache::remember($cacheKey, $cacheTtl, function () use ($request) {
            $healthResponse = app(HealthController::class)($request);
            $healthData = $healthResponse->getData(true);

            return $healthData['checks'] ?? [];
        });

        $totalUsers = User::count();
        $totalSearches = UserSearch::count();

        $llmUsage = app(OpenAiUsageService::class)->getUsage();

        $recentLlmRequests = LlmRequest::query()->with('user')->latest()->limit(10)->get();

        $budgetMonthly = config('flood-watch.llm_budget_monthly', 0);
        $budgetAlertThreshold = $budgetMonthly > 0 ? round($budgetMonthly * 0.8, 2) : null;
        $isOverBudgetAlert = $budgetAlertThreshold !== null
            && $llmUsage['cost_this_month'] !== null
            && $llmUsage['cost_this_month'] >= $budgetAlertThreshold;

        return view('admin.dashboard', [
            'checks' => $checks,
            'totalUsers' => $totalUsers,
            'totalSearches' => $totalSearches,
            'llmUsage' => $llmUsage,
            'recentLlmRequests' => $recentLlmRequests,
            'budgetMonthly' => $budgetMonthly,
            'isOverBudgetAlert' => $isOverBudgetAlert,
        ]);
    }
}
