<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Limits sign-up attempts per IP address (config registration.attempts_per_hour). Fortify
 * registers POST /register itself, so the limit runs here, in the web group. Over the limit
 * the form shows a validation error instead of a bare 429 page.
 */
class ThrottleRegistration
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('POST') || ! $request->routeIs('register.store')) {
            return $next($request);
        }

        $key = 'registration:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, config('registration.attempts_per_hour'))) {
            throw ValidationException::withMessages([
                'email' => __('Too many sign-up attempts. Try again in :minutes minutes.', [
                    'minutes' => (int) ceil(RateLimiter::availableIn($key) / 60),
                ]),
            ]);
        }

        RateLimiter::hit($key, 3600);

        return $next($request);
    }
}
