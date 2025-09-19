<?php

namespace Jdarkins\PulseThrottled\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Jdarkins\PulseThrottled\Pulse\Recorders\ThrottledRecorder;

class ThrottledTracker
{
    public function __construct(
        protected ThrottledRecorder $recorder
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        // Only record when someone gets throttled (429 status) AND package is enabled
        if ($response->getStatusCode() === 429 && config('pulse-throttled.enabled', true)) {
            $this->recorder->recordThrottle([
                'ip' => $request->ip(),
                'path' => $request->path(),
                'method' => $request->method(),
                'limiter_name' => $this->getLimiterName($request),
            ]);
        }
        
        return $response;
    }

    private function getLimiterName(Request $request): string
    {
        $route = $request->route();
        if (!$route) return 'unknown';

        // Look for throttle middleware in route
        $middleware = collect($route->gatherMiddleware())
            ->filter(fn($m) => str_contains($m, 'throttle'))
            ->first();
        
        if ($middleware && str_contains($middleware, ':')) {
            return explode(':', $middleware)[1] ?? 'unknown';
        }
        
        return 'unknown';
    }
}