<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureJsonApi
{
    /**
     * Handle an incoming request. Ensure Accept header and set Content-Type for JSON:API.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/vnd.api+json');

        $response = $next($request);

        if ($response->headers->get('Content-Type') === null
            || str_contains((string) $response->headers->get('Content-Type'), 'application/json')) {
            $response->headers->set('Content-Type', 'application/vnd.api+json');
        }

        return $response;
    }
}
