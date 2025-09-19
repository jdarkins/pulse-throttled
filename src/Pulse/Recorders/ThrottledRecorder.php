<?php

namespace Jdarkins\PulseThrottled\Pulse\Recorders;

use Laravel\Pulse\Contracts\ResolvesUsers;
use Laravel\Pulse\Pulse;

class ThrottledRecorder
{
    public function __construct(
        protected Pulse $pulse,
        protected ResolvesUsers $users
    ) {}

    public function recordThrottle(array $data): void
    {
        $this->pulse->set(
            type: 'throttled_requests',
            key: uniqid('throttle_', true),
            value: json_encode($data),
            timestamp: now()
        );
    }
}
