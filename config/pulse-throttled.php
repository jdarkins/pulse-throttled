<?php

return [
    'enabled' => env('PULSE_ENABLED', true) && env('PULSE_THROTTLED_ENABLED', true),
    'display' => [
        'max_entries' => 10,
        'recent_throttles_limit' => 20,
    ],
];