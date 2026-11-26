<?php

namespace Modules\Customer\Providers;

use Modules\Core\Providers\RouteServiceProvider as BaseRouteServiceProvider;

class RouteServiceProvider extends BaseRouteServiceProvider
{
    protected string $moduleNamespace = 'Modules\Customer\Http\Controllers';
    protected string $moduleName = 'Customer';
    protected string $moduleNameLower = 'customer';
}
