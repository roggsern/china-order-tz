<?php

namespace App\Providers;

use App\Support\Monitoring\AlertNotifierManager;
use App\Support\Monitoring\ErrorMonitorManager;
use App\Support\Monitoring\QueueHealth;
use App\Support\Ops\Backup\BackupDependencyGate;
use App\Support\Ops\Backup\BackupDependencyChecker;
use App\Support\PreventsDestructiveDatabaseCommands;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(BackupDependencyGate::class, BackupDependencyChecker::class);
        $this->app->singleton(ErrorMonitorManager::class);
        $this->app->singleton(AlertNotifierManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(CommandStarting::class, PreventsDestructiveDatabaseCommands::class);

        Event::listen(Looping::class, function (): void {
            try {
                app(QueueHealth::class)->touchWorkerHeartbeat();
            } catch (\Throwable) {
                // Never break the worker loop for monitoring.
            }
        });

        Event::listen(JobFailed::class, function (JobFailed $event): void {
            try {
                app(AlertNotifierManager::class)->alert('Queue job failed', 'warning', [
                    'connection' => $event->connectionName,
                    'queue' => $event->job->getQueue(),
                    'job' => $event->job->resolveName(),
                    'exception' => $event->exception::class,
                    'timestamp' => now()->toIso8601String(),
                ]);
            } catch (\Throwable) {
                //
            }
        });

        RateLimiter::for('admin-login', function (Request $request) {
            return Limit::perMinute(5)->by(
                $request->ip().'|'.strtolower((string) $request->input('email'))
            );
        });

        RateLimiter::for('customer-login', function (Request $request) {
            return Limit::perMinute(5)->by(
                $request->ip().'|'.strtolower((string) $request->input('email'))
            );
        });

        RateLimiter::for('customer-register', function (Request $request) {
            return Limit::perMinute(5)->by(
                $request->ip().'|'.strtolower((string) $request->input('email'))
            );
        });

        RateLimiter::for('webhooks', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // RC1-G4B — named limiters for sensitive customer/admin surfaces.
        RateLimiter::for('checkout', function (Request $request) {
            return Limit::perMinute(30)->by(self::throttleKey($request));
        });

        RateLimiter::for('payments', function (Request $request) {
            return Limit::perMinute(15)->by(self::throttleKey($request));
        });

        RateLimiter::for('cart', function (Request $request) {
            return Limit::perMinute(60)->by(self::throttleKey($request));
        });

        RateLimiter::for('returns', function (Request $request) {
            return Limit::perMinute(10)->by(self::throttleKey($request));
        });

        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinute(20)->by(self::throttleKey($request));
        });

        RateLimiter::for('customer-profile', function (Request $request) {
            return Limit::perMinute(30)->by(self::throttleKey($request));
        });

        RateLimiter::for('admin-profile', function (Request $request) {
            return Limit::perMinute(30)->by(self::throttleKey($request));
        });

        RateLimiter::for('admin-mutations', function (Request $request) {
            return Limit::perMinute(120)->by(self::throttleKey($request));
        });
    }

    private static function throttleKey(Request $request): string
    {
        $user = $request->user();

        if ($user !== null && isset($user->id)) {
            return 'user:'.(string) $user->id;
        }

        return 'ip:'.$request->ip();
    }
}
