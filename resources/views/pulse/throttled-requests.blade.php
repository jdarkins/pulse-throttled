<x-pulse::card :cols="$cols" :rows="$rows" :class="$class" wire:poll.5s="">
    <x-pulse::card-header 
        name="Throttled Requests"
        details="past {{ $this->periodForHumans() }}">
        <x-slot:icon>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" class="stroke-current">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0-10.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.75c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.57-.598-3.75A11.96 11.96 0 0 1 12 2.714Z M12 15.75h.007v.008H12v-.008Z" />
            </svg>
        </x-slot:icon>
        
        <x-slot:actions>
            <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                {{ $throttledData['total_throttles'] }} throttles from {{ $throttledData['unique_ips'] }} IPs
            </div>
        </x-slot:actions>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand" wire:poll.5s="">
        @if($throttledData['total_throttles'] > 0)
            <div class="grid grid-cols-3 gap-3 mx-px mb-px">
                <div class="col-span-1 mr-2">
                    <div class="flex mb-4">
                        <button 
                            wire:click="switchTab('urls')"
                            class="flex-1 mr-2 py-2 text-sm font-medium text-center  {{ $activeTab === 'urls' ? "bg-gray-50 dark:bg-gray-800 rounded" : "" }}">
                            Most Throttled URLs
                        </button>
                        <button 
                            wire:click="switchTab('ips')"
                            class="flex-1 ml-2 py-2 text-sm font-medium text-center {{ $activeTab === "ips" ? "bg-gray-50 dark:bg-gray-800 rounded" : "" }}">
                            Most Throttled IPs
                        </button>
                    </div>
                    
                    <div>
                        @if($activeTab === 'urls')
                            <div>
                                <x-pulse::table>
                                    <colgroup>
                                        <col width="100%" />
                                        <col width="0%" />
                                        <col width="0%" />
                                    </colgroup>
                                    <x-pulse::thead>
                                        <tr>
                                            <x-pulse::th>URL Path</x-pulse::th>
                                            <x-pulse::th class="text-right">Last</x-pulse::th>
                                            <x-pulse::th class="text-right">Throttles</x-pulse::th>
                                        </tr>
                                    </x-pulse::thead>
                                    <tbody>
                                        @foreach($throttledData['url_stats'] as $url)
                                            <tr wire:key="{{ $url['path'] }}-spacer" class="h-2 first:h-0"></tr>
                                            <tr wire:key="{{ $url['path'] }}-row">
                                                <x-pulse::td class="max-w-[1px]">
                                                    <code class="block text-xs text-gray-900 dark:text-gray-100 truncate font-bold" title="/{{ $url['path'] }}">
                                                        /{{ $url['path'] }}
                                                    </code>
                                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                        {{ $url['ips'] }} different IPs
                                                    </p>
                                                </x-pulse::td>
                                                <x-pulse::td numeric class="text-gray-700 dark:text-gray-300 font-bold text-xs">
                                                    {{ \Carbon\Carbon::createFromTimestamp($url['last_throttled'])->diffForHumans() }}
                                                </x-pulse::td>
                                                <x-pulse::td numeric class="text-gray-700 dark:text-gray-300 font-bold">
                                                    {{ number_format($url['throttles']) }}
                                                </x-pulse::td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </x-pulse::table>
                            </div>
                        @else
                            <div>
                                <x-pulse::table>
                                    <colgroup>
                                        <col width="100%" />
                                        <col width="0%" />
                                        <col width="0%" />
                                    </colgroup>
                                    <x-pulse::thead>
                                        <tr>
                                            <x-pulse::th>IP Address</x-pulse::th>
                                            <x-pulse::th class="text-right">Last</x-pulse::th>
                                            <x-pulse::th class="text-right">Throttles</x-pulse::th>
                                        </tr>
                                    </x-pulse::thead>
                                    <tbody>
                                        @foreach($throttledData['ip_stats'] as $ip)
                                            <tr wire:key="{{ $ip['ip'] }}-spacer" class="h-2 first:h-0"></tr>
                                            <tr wire:key="{{ $ip['ip'] }}-row">
                                                <x-pulse::td class="max-w-[1px]">
                                                    <code class="block text-xs text-gray-900 dark:text-gray-100 font-bold" title="{{ $ip['ip'] }}">
                                                        {{ $ip['ip'] }}
                                                    </code>
                                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                        {{ $ip['urls'] }} different URLs
                                                    </p>
                                                </x-pulse::td>
                                                <x-pulse::td numeric class="text-gray-700 dark:text-gray-300 font-bold text-xs">
                                                    {{ \Carbon\Carbon::createFromTimestamp($ip['last_throttled'])->diffForHumans() }}
                                                </x-pulse::td>
                                                <x-pulse::td numeric class="text-gray-700 dark:text-gray-300 font-bold">
                                                    {{ number_format($ip['throttles']) }}
                                                </x-pulse::td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </x-pulse::table>
                            </div>
                        @endif
                    </div>
                </div>
                
                <div class="col-span-2 ml-2">
                    <h4 class="text-lg font-medium mb-3">Recent Blocks</h4>
                    <div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-100 dark:bg-gray-700">
                        <tr>
                            <th class="px-3 py-2 text-left">Time</th>
                            <th class="px-3 py-2 text-left">IP Address</th>
                            <th class="px-3 py-2 text-left">Method</th>
                            <th class="px-3 py-2 text-left">Path</th>
                            <th class="px-3 py-2 text-left">Limiter</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($throttledData['recent_throttles'] as $throttle)
                            <tr class="border-b dark:border-gray-700">
                                <td class="px-3 py-2">{{ \Carbon\Carbon::createFromTimestamp($throttle['timestamp'])->format('H:i:s') }}</td>
                                <td class="px-3 py-2 font-mono">{{ $throttle['ip'] }}</td>
                                <td class="px-3 py-2">
                                    <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">
                                        {{ $throttle['method'] }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 font-mono">/{{ $throttle['path'] }}</td>
                                <td class="px-3 py-2">
                                    <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs">
                                        {{ $throttle['limiter_name'] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                    </div>
                </div>
            </div>
        @else
            @if($isDisabled)
                <div class="flex items-center justify-center h-32">
                    <div class="text-center">
                        <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-yellow-100 rounded-full">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                        </div>
                        <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-1">Package Disabled</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Throttled request tracking is disabled.<br>
                            Set <code class="px-1 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-xs">PULSE_THROTTLED_ENABLED=true</code> to enable.
                        </p>
                    </div>
                </div>
            @else
                <x-pulse::no-results />
            @endif
        @endif
    </x-pulse::scroll>
</x-pulse::card>