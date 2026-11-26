<?php

namespace Modules\Customer\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Modules\Customer\Policies\CustomerPolicy;
use Nwidart\Modules\Support\ModuleServiceProvider;

class CustomerServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Customer';
    protected string $nameLower = 'customer';

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        Gate::policy(User::class, CustomerPolicy::class);
    }
}
