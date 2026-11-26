<?php

namespace Modules\Core\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Models\Setting;
use Modules\Core\Observers\SettingObserver;
use Modules\Core\Policies\DashboardPolicy;
use Modules\Core\Policies\ReportPolicy;
use Modules\Core\Policies\SettingPolicy;
use Nwidart\Modules\Support\ModuleServiceProvider;

class CoreServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Core';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'core';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        Setting::observe(SettingObserver::class);

        Gate::define('viewDashboard', [DashboardPolicy::class, 'view']);
        Gate::define('viewReports', [ReportPolicy::class, 'view']);
        Gate::define('viewSalesReports', [ReportPolicy::class, 'viewSales']);
        Gate::define('viewFinancialReports', [ReportPolicy::class, 'viewFinancial']);
        Gate::define('viewSettings', [SettingPolicy::class, 'viewAny']);
        Gate::define('updateSettings', [SettingPolicy::class, 'update']);
        Gate::define('uploadSettingFile', [SettingPolicy::class, 'uploadFile']);
    }

    /**
     * Define module schedules.
     * 
     * @param $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
