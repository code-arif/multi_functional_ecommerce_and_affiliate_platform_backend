<?php

namespace Modules\Promotions\Providers;

use Modules\Promotions\Models\Banner;
use Modules\Promotions\Models\Coupon;
use Modules\Promotions\Models\Promotion;
use Modules\Promotions\Observers\BannerObserver;
use Modules\Promotions\Policies\BannerPolicy;
use Modules\Promotions\Policies\CouponPolicy;
use Modules\Promotions\Policies\PromotionPolicy;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $policies = [
        Promotion::class => PromotionPolicy::class,
        Banner::class    => BannerPolicy::class,
        Coupon::class    => CouponPolicy::class,
    ];

    protected $listen = [
        // Future: PromotionApplied::class => [NotifyUser::class],
    ];

    protected $observers = [
        Banner::class => [BannerObserver::class],
    ];

    public function boot(): void
    {
        parent::boot();
    }
}
