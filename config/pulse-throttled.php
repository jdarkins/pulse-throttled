<?php

return [
    'enabled' => env('PULSE_ENABLED', true) && env('PULSE_THROTTLED_ENABLED', true),
    'auto_register_middleware' => env('PULSE_THROTTLED_AUTO_MIDDLEWARE', true),
    'display' => [
        'max_entries' => 10,
        'recent_throttles_limit' => 20,
    ],
];