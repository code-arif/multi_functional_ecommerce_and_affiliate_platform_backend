<?php

namespace Modules\Vendor\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Vendor\Events\VendorApprovedEvent;
use Modules\Vendor\Events\VendorRegisteredEvent;
use Modules\Vendor\Events\VendorRejectedEvent;
use Modules\Vendor\Listeners\SendVendorNotification;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        VendorRegisteredEvent::class => [
            SendVendorNotification::class,
        ],
        VendorApprovedEvent::class => [
            SendVendorNotification::class,
        ],
        VendorRejectedEvent::class => [
            SendVendorNotification::class,
        ],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = true;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
