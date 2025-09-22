<?php

namespace Jdarkins\PulseThrottled;

use Jdarkins\PulseThrottled\Http\Middleware\ThrottledTracker;
use Jdarkins\PulseThrottled\Livewire\Pulse\ThrottledRequests;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class PulseThrottledServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/pulse-throttled.php', 'pulse-throttled');
    }

    public function boot(): void
    {
        // Load views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'pulse-throttled');

        // Publish config
        $this->publishes([
            __DIR__.'/../config/pulse-throttled.php' => config_path('pulse-throttled.php'),
        ], 'pulse-throttled-config');

        // Publish views
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/pulse-throttled'),
        ], 'pulse-throttled-views');

        // Auto-register middleware (if enabled and package is enabled)
        if ($this->app->runningInConsole() === false 
            && config('pulse-throttled.enabled', true)
            && config('pulse-throttled.auto_register_middleware', true)) {
            $kernel = $this->app->make(Kernel::class);
            $kernel->pushMiddleware(ThrottledTracker::class);
        }

        // Auto-register Livewire component
        if (class_exists(Livewire::class)) {
            Livewire::component('pulse.throttled-requests', 
                ThrottledRequests::class
            );
        }
    }
}