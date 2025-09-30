<?php

namespace Jdarkins\PulseThrottled\Livewire\Pulse;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Laravel\Pulse\Livewire\Card;
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
                    'ip_stats' => new Collection(),
                    'url_stats' => new Collection(),
                    'recent_throttles' => new Collection(),
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
        // Get entries directly from the database since aggregation isn't working
        $startTime = now()->sub($this->periodAsInterval())->timestamp;
        $endTime = now()->timestamp;
        
        // Query raw entries and group them manually
        $entries = \DB::table('pulse_entries')
            ->where('type', 'throttled_request')
            ->where('timestamp', '>=', $startTime)
            ->where('timestamp', '<=', $endTime)
            ->orderBy('timestamp', 'desc')
            ->get(['key', 'timestamp']);
        
        // If no data found, return empty result early
        if ($entries->isEmpty()) {
            return [
                'total_throttles' => 0,
                'unique_ips' => 0,
                'unique_paths' => 0,
                'url_stats' => new Collection(),
                'ip_stats' => new Collection(),
                'recent_throttles' => new Collection(),
            ];
        }
        
        // Process the raw entries and count them by key
        $groupedEntries = $entries->groupBy('key')->map(function ($keyEntries, $key) {
            return (object) [
                'key' => $key,
                'count' => $keyEntries->count(),
                'latest_timestamp' => $keyEntries->max('timestamp'), // Track latest occurrence
                'earliest_timestamp' => $keyEntries->min('timestamp'), // Track earliest occurrence
            ];
        })->sortByDesc('latest_timestamp'); // Sort by most recent activity
        
        // Parse the composite key format: ip|method|path
        $processedStats = $groupedEntries->map(function ($row) {
            // Parse the composite key: ip|method|path
            $keyParts = explode('|', $row->key);
            $ip = $keyParts[0] ?? 'unknown';
            $method = $keyParts[1] ?? 'GET';
            $path = $keyParts[2] ?? $row->key;
            
            return [
                'ip' => $ip,
                'method' => $method,
                'path' => $path,
                'count' => $row->count,
                'full_key' => $row->key,
                'latest_timestamp' => $row->latest_timestamp,
                'earliest_timestamp' => $row->earliest_timestamp,
            ];
        });

        // Group by path for URL stats
        $urlStats = $processedStats->groupBy('path')->map(function ($pathEntries, $path) {
            return [
                'path' => $path,
                'throttles' => (int) $pathEntries->sum('count'),
                'ips' => $pathEntries->pluck('ip')->unique()->count(),
                'last_throttled' => $pathEntries->max('latest_timestamp'), // Use real timestamp
            ];
        })->sortByDesc('last_throttled')->take(20); // Sort by most recent activity

        // Group by IP for IP stats
        $ipStats = $processedStats->groupBy('ip')->map(function ($ipEntries, $ip) {
            $pathCounts = $ipEntries->countBy('path');
            return [
                'ip' => $ip,
                'throttles' => (int) $ipEntries->sum('count'),
                'urls' => $ipEntries->pluck('path')->unique()->count(),
                'last_throttled' => $ipEntries->max('latest_timestamp'), // Use real timestamp
                'top_url' => $pathCounts->keys()->first() ?? 'unknown',
            ];
        })->sortByDesc('last_throttled')->take(20); // Sort by most recent activity

        // Create recent throttles list - use individual entries for more accurate display
        $recentThrottles = $entries->map(function($entry) {
            // Parse the composite key for each individual entry
            $keyParts = explode('|', $entry->key);
            $ip = $keyParts[0] ?? 'unknown';
            $method = $keyParts[1] ?? 'GET';
            $path = $keyParts[2] ?? $entry->key;
            
            return [
                'path' => $path,
                'method' => $method,
                'ip' => $ip,
                'timestamp' => $entry->timestamp,
                'limiter_name' => $this->guessLimiterFromPath($path),
            ];
        })->take(20);

        $totalThrottles = (int) $processedStats->sum('count');
        $uniqueIps = $processedStats->pluck('ip')->unique()->count();

        return [
            'total_throttles' => $totalThrottles,
            'unique_ips' => $uniqueIps,
            'unique_paths' => $processedStats->pluck('path')->unique()->count(),
            'url_stats' => $urlStats,
            'ip_stats' => $ipStats,
            'recent_throttles' => $recentThrottles,
        ];
    }

    /**
     * Guess the limiter name from the path
     */
    protected function guessLimiterFromPath($path)
    {
        // Map common paths to likely limiters based on your API setup
        $limiters = [
            'api/heavy' => '1,1',
            'api/medium' => '5,1', 
            'api/light' => '60,1',
        ];

        return $limiters[$path] ?? 'unknown';
    }
}
