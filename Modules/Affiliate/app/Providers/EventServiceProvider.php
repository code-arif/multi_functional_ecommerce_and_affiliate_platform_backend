<?php

namespace Modules\Affiliate\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Affiliate\Models\AffiliateProduct;
use Modules\Affiliate\Policies\AffiliateProductPolicy;

class EventServiceProvider extends ServiceProvider
{
    protected $policies = [
        AffiliateProduct::class => AffiliateProductPolicy::class,
    ];

    protected $listen = [
        // Future: AffiliateConversionApproved::class => [NotifyAffiliate::class],
    ];

    public function boot(): void
    {
        parent::boot();
    }
}
