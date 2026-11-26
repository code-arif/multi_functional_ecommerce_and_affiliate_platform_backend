<?php

namespace Modules\RBAC\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\RBAC\Policies\PermissionPolicy;
use Modules\RBAC\Policies\RolePolicy;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Model policies for the RBAC module.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Role::class       => RolePolicy::class,
        Permission::class => PermissionPolicy::class,
    ];

    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [];

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
