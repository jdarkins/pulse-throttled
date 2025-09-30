<?php

namespace Jdarkins\PulseThrottled\Pulse\Recorders;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Laravel\Pulse\Concerns\ConfiguresAfterResolving;
use Laravel\Pulse\Pulse;
use Laravel\Pulse\Recorders\Concerns\Sampling;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ThrottledRecorder
{
    use Sampling, ConfiguresAfterResolving;

    /**
     * Create a new recorder instance.
     */
    public function __construct(
        protected Pulse $pulse,
    ) {
        //
    }

    /**
     * Register the recorder.
     */
    public function register(callable $record, Application $app): void
    {
        $this->afterResolving(
            $app,
            Kernel::class,
            fn (Kernel $kernel) => $kernel->whenRequestLifecycleIsLongerThan(-1, $record)
        );
    }

    /**
     * Record the request if it was throttled.
     */
    public function record(Carbon $startedAt, Request $request, Response $response): void
    {
        // Only record throttled requests (429 status)
        if ($response->getStatusCode() !== 429 || !$this->shouldSample()) {
            return;
        }

        $limiterName = $this->getLimiterName($request);

        // Create a unique key that includes IP and path for better tracking
        $uniqueKey = $request->ip() . '|' . $request->method() . '|' . $request->path();

        // Record the main throttled request entry
        $entry = $this->pulse->record(
            type: 'throttled_request',
            key: $uniqueKey,
            value: null,
            timestamp: $startedAt->getTimestamp()
        );

        // Add detailed tags for filtering and analysis
        $entry->tag([
            'ip:' . $request->ip(),
            'method:' . $request->method(), 
            'path:' . $request->path(),
            'limiter:' . $limiterName,
            'user_agent:' . substr($request->userAgent() ?? 'unknown', 0, 50), // Truncate UA
        ]);

        $result = $entry->count();
    }

    /**
     * Get the limiter name from the request route.
     */
    protected function getLimiterName(Request $request): string
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
