<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ThrottleRegistration;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Traefik reaches the app from the Docker proxy network and passes Cloudflare's
        // X-Forwarded-For on, so the client IP is the visitor's. Only the client IP and the
        // scheme are trusted: X-Forwarded-Host and -Port reach the app from the visitor
        // unchanged, and trusting them would let anyone change the host in generated links.
        $middleware->trustProxies(
            at: ['172.16.0.0/12'],
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->redirectUsersTo(fn (Request $request) => config('fortify.home'));

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            ThrottleRegistration::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Removes sign-ups that never verified their e-mail (User::prunable).
        $schedule->command('model:prune', ['--model' => [User::class]])
            ->dailyAt('05:15')
            ->timezone('Europe/Warsaw')
            ->withoutOverlapping(30)
            ->onOneServer()
            ->environments(['production']);

        $schedule->command('backup:run')
            ->dailyAt('03:45')
            ->timezone('Europe/Warsaw')
            ->withoutOverlapping(120)
            ->onOneServer()
            ->environments(['production']);

        $schedule->command('backup:clean')
            ->dailyAt('04:15')
            ->timezone('Europe/Warsaw')
            ->withoutOverlapping(60)
            ->onOneServer()
            ->environments(['production']);

        $schedule->command('backup:monitor')
            ->dailyAt('04:45')
            ->timezone('Europe/Warsaw')
            ->withoutOverlapping(30)
            ->onOneServer()
            ->environments(['production']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
