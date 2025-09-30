<?php

namespace Jdarkins\PulseThrottled\Livewire\Pulse;

use Illuminate\Contracts\View\View;
use Illuminate\Config\Repository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Jdarkins\PulseThrottled\Pulse\Recorders\ThrottledRecorder;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

#[Lazy]
class ThrottledRequests extends Card
{
    public $activeTab = 'urls';
    
    private const RECORDER_CLASS = ThrottledRecorder::class;

    public function switchTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function render(): View
    {
        if (! Config::get('pulse.recorders.'.self::RECORDER_CLASS.'.enabled', true)) {
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
        $startTime = now()->sub($this->periodAsInterval())->timestamp;
        $endTime = now()->timestamp;
        
        $entries = \DB::table('pulse_entries')
            ->where('type', 'throttled_request')
            ->where('timestamp', '>=', $startTime)
            ->where('timestamp', '<=', $endTime)
            ->orderBy('timestamp', 'desc')
            ->get(['key', 'timestamp']);
        
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
        
        $groupedEntries = $entries->groupBy('key')->map(function ($keyEntries, $key) {
            return (object) [
                'key' => $key,
                'count' => $keyEntries->count(),
                'latest_timestamp' => $keyEntries->max('timestamp'),
            ];
        })->sortByDesc('latest_timestamp');
        
        $processedStats = $groupedEntries->map(function ($row) {
            $parsed = $this->parseKey($row->key);
            
            return [
                ...$parsed,
                'count' => $row->count,
                'latest_timestamp' => $row->latest_timestamp,
            ];
        });

        $urlStats = $processedStats->groupBy('path')->map(function ($pathEntries, $path) {
            return [
                'path' => $path,
                'throttles' => (int) $pathEntries->sum('count'),
                'ips' => $pathEntries->pluck('ip')->unique()->count(),
                'last_throttled' => $pathEntries->max('latest_timestamp'), // Use real timestamp
            ];
        })->sortByDesc('last_throttled')->take(Config::get('pulse.recorders.'.self::RECORDER_CLASS.'.display_limit', 20));

        $ipStats = $processedStats->groupBy('ip')->map(function ($ipEntries, $ip) {
            $pathCounts = $ipEntries->countBy('path');
            return [
                'ip' => $ip,
                'throttles' => (int) $ipEntries->sum('count'),
                'urls' => $ipEntries->pluck('path')->unique()->count(),
                'last_throttled' => $ipEntries->max('latest_timestamp'), // Use real timestamp
                'top_url' => $pathCounts->keys()->first() ?? 'unknown',
            ];
        })->sortByDesc('last_throttled')->take(Config::get('pulse.recorders.'.self::RECORDER_CLASS.'.display_limit', 20));

        $recentThrottles = $entries->map(function($entry) {
            $parsed = $this->parseKey($entry->key);
            
            return [
                ...$parsed,
                'timestamp' => $entry->timestamp,
                'limiter_name' => $parsed['limiter'],
            ];
        })->take(Config::get('pulse.recorders.'.self::RECORDER_CLASS.'.display_limit', 20));

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

    protected function parseKey(string $key): array
    {
        $keyParts = explode('|', $key);
        return [
            'ip' => $keyParts[0] ?? 'unknown',
            'method' => $keyParts[1] ?? 'unknown',
            'path' => $keyParts[2] ?? 'unknown',
            'limiter' => $keyParts[3] ?? 'unknown',
        ];
    }
}
