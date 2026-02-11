<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFloodWatchSession
{
    /**
     * Allow polygons and river-levels only when the request has a session
     * that was marked by loading the flood-watch dashboard (same-origin front end).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('flood_watch_loaded') !== true) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}
