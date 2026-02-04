<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleFloodWatch
{
    /**
     * Routes excluded from rate limiting (e.g. health checks for load balancers).
     */
    private const EXCLUDED_PATHS = ['/health', '/up'];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isExcluded($request)) {
            return $next($request);
        }

        $key = 'flood-watch:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 60)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'message' => 'Too many requests. Please try again in a minute.',
            ], 429)->withHeaders([
                'Retry-After' => $seconds,
            ]);
        }

        RateLimiter::hit($key, 60);

        $response = $next($request);

        return $this->addHeaders($response, $key);
    }

    private function isExcluded(Request $request): bool
    {
        $path = $request->path();

        foreach (self::EXCLUDED_PATHS as $excluded) {
            if ($path === ltrim($excluded, '/')) {
                return true;
            }
        }

        return false;
    }

    private function addHeaders(Response $response, string $key): Response
    {
        $response->headers->set('X-RateLimit-Limit', 60);
        $response->headers->set('X-RateLimit-Remaining', max(0, 60 - RateLimiter::attempts($key)));

        return $response;
    }
}
