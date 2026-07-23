<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureAdminIsActive;
use App\Http\Middleware\EnsureAdminPermission;
use App\Http\Middleware\EnsureUser;
use App\Http\Middleware\EnsureUserIsActive;
use App\Support\Monitoring\ErrorMonitorManager;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->redirectGuestsTo(fn (): ?string => null);

        $middleware->appendToGroup('api', [
            AssignRequestId::class,
        ]);

        $middleware->alias([
            'admin.active' => EnsureAdminIsActive::class,
            'admin.permission' => EnsureAdminPermission::class,
            'ensure.admin' => EnsureAdmin::class,
            'ensure.user' => EnsureUser::class,
            'user.active' => EnsureUserIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->reportable(function (\Throwable $e): void {
            if (! config('monitoring.enabled', true)) {
                return;
            }

            if ($e instanceof \Illuminate\Validation\ValidationException
                || $e instanceof AuthenticationException
                || $e instanceof \Illuminate\Auth\Access\AuthorizationException
                || $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                || $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return;
            }

            try {
                app(ErrorMonitorManager::class)->driver()->capture($e, [
                    'url' => request()?->path(),
                    'method' => request()?->method(),
                    'request_id' => request()?->attributes->get('request_id')
                        ?? request()?->headers->get('X-Request-Id'),
                ]);
            } catch (\Throwable) {
                // Never break exception reporting pipeline.
            }
        });

        $exceptions->shouldRenderJsonWhen(function (Request $request, \Throwable $throwable) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage() ?: 'Unauthenticated.',
                ], 401);
            }
        });

        // RC1-G4B — never leak exception messages/stack in production API responses.
        $exceptions->render(function (\Throwable $exception, Request $request) {
            if (! app()->environment('production')) {
                return null;
            }

            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            if ($exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                return null;
            }

            if ($exception instanceof \Illuminate\Validation\ValidationException) {
                return null;
            }

            if ($exception instanceof AuthenticationException) {
                return null;
            }

            if ($exception instanceof \Illuminate\Auth\Access\AuthorizationException) {
                return null;
            }

            if ($exception instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return null;
            }

            return response()->json([
                'message' => 'Server Error',
            ], 500);
        });
    })->create();
