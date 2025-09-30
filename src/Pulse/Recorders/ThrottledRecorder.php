<?php

namespace Jdarkins\PulseThrottled\Pulse\Recorders;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Config\Repository;
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

    public function __construct(
        protected Pulse $pulse,
        protected Repository $config
    ) {
        //
    }

    public function register(callable $record, Application $app): void
    {
        if (! $this->config->get('pulse.recorders.'.self::class.'.enabled', true)) {
            return;
        }

        $this->afterResolving(
            $app,
            Kernel::class,
            fn (Kernel $kernel) => $kernel->whenRequestLifecycleIsLongerThan(-1, $record)
        );
    }

    public function record(Carbon $startedAt, Request $request, Response $response): void
    {
        if ($response->getStatusCode() !== 429 || !$this->shouldSample()) {
            return;
        }

        $uniqueKey = $request->ip() . '|' . $request->method() . '|' . $request->path() . '|' . $this->getLimiterName($request);

        $this->pulse->record(
            type: 'throttled_request',
            key: $uniqueKey,
            value: null,
            timestamp: $startedAt->getTimestamp()
        )->count();
    }

    protected function getLimiterName(Request $request): string
    {
        $route = $request->route();
        if (!$route) return 'unknown';

        $middleware = collect($route->gatherMiddleware())
            ->filter(fn($m) => str_contains($m, 'throttle'))
            ->first();
        
        if ($middleware && str_contains($middleware, ':')) {
            return explode(':', $middleware)[1] ?? 'unknown';
        }
        
        return 'unknown';
    }
}
