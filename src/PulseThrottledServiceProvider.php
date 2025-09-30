<?php

namespace Jdarkins\PulseThrottled;

use Jdarkins\PulseThrottled\Livewire\Pulse\ThrottledRequests;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class PulseThrottledServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'pulse-throttled');
        
        Livewire::component('pulse.throttled-requests', ThrottledRequests::class);
    }
}