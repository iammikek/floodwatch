<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HealthController;
use App\Models\User;
use App\Models\UserSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        if (! $request->user()) {
            abort(403);
        }

        Gate::authorize('accessAdmin');

        $healthResponse = app(HealthController::class)($request);
        $healthData = $healthResponse->getData(true);
        $checks = $healthData['checks'] ?? [];

        $totalUsers = User::count();
        $totalSearches = UserSearch::count();

        return view('admin.dashboard', [
            'checks' => $checks,
            'totalUsers' => $totalUsers,
            'totalSearches' => $totalSearches,
        ]);
    }
}
