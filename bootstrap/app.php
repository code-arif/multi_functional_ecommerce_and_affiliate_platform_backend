<?php

use Modules\Core\Http\Middleware\AdminMiddleware;
use Modules\Core\Http\Middleware\VendorAuthMiddleware;
use Modules\Core\Http\Middleware\VendorMiddleware;
use Modules\Core\Http\Middleware\PermissionMiddleware;
use Modules\Core\Http\Middleware\RoleMiddleware;
use App\Exceptions\Handler as ApiExceptionHandler;
use App\Http\Middleware\BannedUserMiddleware;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\MaintenanceModeMiddleware;
use App\Http\Middleware\SanitizeInput;
use App\Http\Middleware\SecurityHeaders;
use App\Jobs\CleanExpiredCarts;
use App\Jobs\GenerateDailyReport;
use App\Jobs\WarmCacheJob;
use App\Providers\AppServiceProvider;
use App\Providers\EventServiceProvider;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // Global Middleware (every request)
        $middleware->append(SecurityHeaders::class);
        $middleware->append(SanitizeInput::class);

        // API Middleware Group: ForceJsonResponse MUST run before Authenticate
        $middleware->api(
            prepend: [
                ForceJsonResponse::class,
            ],
            append: [
                ThrottleRequests::class . ':api',
                // ApiRequestLogger::class,
                MaintenanceModeMiddleware::class,
            ]
        );

        // Never redirect unauthenticated API requests to a web 'login' route
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/');

        // Authenticated Routes
        $middleware->alias([
            'auth' => Authenticate::class,
            'admin' => AdminMiddleware::class,
            'vendor' => VendorMiddleware::class,
            'vendor.auth' => VendorAuthMiddleware::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'banned' => BannedUserMiddleware::class,
            'throttle' => ThrottleRequests::class,
            'verified' => EnsureEmailIsVerified::class,
        ]);

        // CORS
        $middleware->validateCsrfTokens(except: ['api/*']);

        // Sanctum Token Auth
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        ApiExceptionHandler::configure($exceptions);
    })->withProviders([
        AppServiceProvider::class,
        EventServiceProvider::class,
    ])
    ->withSchedule(function (Schedule $schedule) {
        // Reports
        $schedule->job(new GenerateDailyReport(), 'reports')
            ->dailyAt('00:05')
            ->withoutOverlapping()
            ->onFailure(function () {
                Log::error('GenerateDailyReport schedule failed');
            });

        // Cart Cleanup
        $schedule->job(new CleanExpiredCarts(), 'default')
            ->dailyAt('00:15')
            ->withoutOverlapping();

        // Sitemap
        $schedule->command('sitemap:generate')
            ->dailyAt('00:30')
            ->withoutOverlapping();

        // Cache Warm
        $schedule->job(new WarmCacheJob(), 'default')
            ->hourly()
            ->withoutOverlapping();

        // Rating Recalculation
        $schedule->command('products:recalculate-ratings')
            ->weekly()
            ->sundays()
            ->at('02:00')
            ->withoutOverlapping();

        // Queue Monitor
        $schedule->command('queue:prune-failed --hours=168') // 7 days
            ->weekly();

        // Log cleanup
        $schedule->command('log:clear')
            ->monthly();
    })
    ->create();
