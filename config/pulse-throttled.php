<?php

return [
    'enabled' => env('PULSE_THROTTLED_ENABLED', false),
    'auto_register_middleware' => env('PULSE_THROTTLED_AUTO_MIDDLEWARE', true),
    'display' => [
        'max_entries' => 10,
        'recent_throttles_limit' => 20,
    ],
];