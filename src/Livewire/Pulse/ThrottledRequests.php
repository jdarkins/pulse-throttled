<?php

namespace Jdarkins\PulseThrottled\Livewire\Pulse;

use Illuminate\Contracts\View\View;
use Laravel\Pulse\Livewire\Card;
use Laravel\Pulse\Facades\Pulse;
use Livewire\Attributes\Lazy;

#[Lazy]
class ThrottledRequests extends Card
{
    public $activeTab = 'urls';

    public function switchTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function render(): View
    {
        // Return empty data with disabled flag if disabled
        if (!config('pulse-throttled.enabled', true)) {
            return view('pulse-throttled::pulse.throttled-requests', [
                'throttledData' => [
                    'total_throttles' => 0,
                    'unique_ips' => 0,
                    'ip_stats' => collect(),
                    'url_stats' => collect(),
                    'recent_throttles' => collect(),
                ],
                'time' => now(),
                'runAt' => now(),
                'isDisabled' => true,
            ]);
        }

        [$throttledData, $time, $runAt] = $this->remember(
            fn () => $this->getThrottledRequestsData(),
            $this->periodAsInterval()
        );

        return view('pulse-throttled::pulse.throttled-requests', [
            'throttledData' => $throttledData,
            'time' => $time,
            'runAt' => $runAt,
            'isDisabled' => false,
        ]);
    }

    protected function getThrottledRequestsData()
    {
        $period = $this->periodAsInterval();
        $startTime = now()->sub($period)->timestamp;

        $pulse = Pulse::getFacadeRoot();

        $throttledRequests = $pulse->values('throttled_requests')
            ->where('timestamp', '>=', $startTime)
            ->map(function ($entry) {
                $data = json_decode($entry->value, true);
                $data['timestamp'] = $entry->timestamp; // Use Pulse table timestamp
                return $data;
            })
            ->sortByDesc('timestamp');

        return $this->processThrottledData($throttledRequests);
    }

    protected function processThrottledData($throttledRequests)
    {
        $ipStats = $throttledRequests->groupBy('ip')->map(function ($requests, $ip) {
            return [
                'ip' => $ip,
                'throttles' => $requests->count(),
                'urls' => $requests->pluck('path')->unique()->count(),
                'last_throttled' => $requests->max('timestamp'),
                'top_url' => $requests->countBy('path')->keys()->first(),
            ];
        })->sortByDesc('throttles');

        $urlStats = $throttledRequests->groupBy('path')->map(function ($requests, $path) {
            return [
                'path' => $path,
                'throttles' => $requests->count(),
                'ips' => $requests->pluck('ip')->unique()->count(),
                'last_throttled' => $requests->max('timestamp'),
            ];
        })->sortByDesc('throttles');

        return [
            'total_throttles' => $throttledRequests->count(),
            'unique_ips' => $throttledRequests->pluck('ip')->unique()->count(),
            'ip_stats' => $ipStats->take(config('pulse-throttled.display.max_entries', 10)),
            'url_stats' => $urlStats->take(config('pulse-throttled.display.max_entries', 10)),
            'recent_throttles' => $throttledRequests->take(config('pulse-throttled.display.recent_throttles_limit', 20)),
        ];
    }
}
