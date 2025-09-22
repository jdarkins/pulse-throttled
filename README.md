# Laravel Pulse Throttled Requests Tracker

A Laravel Pulse card to track users who are being throttled by your Rate Limiting.

![Throttled Requests Card Example](assets/pulse-throttled-example.png)

## Installation

1. Install the package via Composer:

```bash
composer require jdarkins/pulse-throttled
```

2. **Add the card to your Pulse dashboard:**

Add this to your Pulse dashboard view (usually `resources/views/pulse/dashboard.blade.php`):

```blade
<livewire:pulse.throttled-requests cols="full" rows="2" />
```

That's it! The middleware will be automatically registered.

### Manual Middleware Registration (Optional)

If you prefer to manually register the middleware, you can disable auto-registration and add it yourself:

1. Set `PULSE_THROTTLED_AUTO_MIDDLEWARE=false` in your `.env` file
2. Add the middleware to `app/Http/Kernel.php`:

```php
// app/Http/Kernel.php
protected $middleware = [
    // ... other middleware
    \Jdarkins\PulseThrottled\Http\Middleware\ThrottledTracker::class,
];
```

## Optional Configuration

Publish the configuration file (optional):

```bash
php artisan vendor:publish --tag=pulse-throttled-config
```

Publish the views for customization (optional):

```bash
php artisan vendor:publish --tag=pulse-throttled-views
```

## Usage

Once installed, the package will automatically track requests that receive a 429 (Too Many Requests) response from your throttle middleware. The Pulse card will show:

- Most throttled URLs
- Most throttled IP addresses  
- Recent throttling events
- Total throttle count and unique IPs

### Configuration Options

```php
// config/pulse-throttled.php
return [
    'enabled' => env('PULSE_ENABLED', true) && env('PULSE_THROTTLED_ENABLED', true), // Package can be disabled manually or via Pulse's global toggle
    'auto_register_middleware' => env('PULSE_THROTTLED_AUTO_MIDDLEWARE', true),      // Auto-register middleware
    'display' => [
        'max_entries' => 10,            // Max IPs/URLs to show in lists
        'recent_throttles_limit' => 20, // Max recent events to show
    ],
];
```

### Environment Variables

Add these to your `.env` file to customize behavior:

```bash
# Disable throttle tracking entirely
PULSE_THROTTLED_ENABLED=false

# Disable automatic middleware registration (register manually instead)
PULSE_THROTTLED_AUTO_MIDDLEWARE=false
```

**Note**: This package respects Laravel Pulse's global `PULSE_ENABLED` setting. If Pulse is disabled globally, this package will also be disabled.

## Requirements

- PHP ^8.1
- Laravel ^10.0|^11.0
- Laravel Pulse ^1.0
- Livewire ^3.0

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.