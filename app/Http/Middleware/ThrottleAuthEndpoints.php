<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleAuthEndpoints
{
    /** path => [nama, maks percobaan, jendela dalam menit] */
    private const LIMITS = [
        'forgot-password' => ['password-reset', 3, 60],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('post')) {
            return $next($request);
        }

        foreach (self::LIMITS as $path => [$name, $max, $minutes]) {
            if (! $request->is($path)) {
                continue;
            }

            $key = $name.'|'.strtolower((string) $request->input('email')).'|'.$request->ip();

            if (RateLimiter::tooManyAttempts($key, $max)) {
                abort(429, 'Terlalu banyak percobaan. Silakan coba lagi nanti.');
            }

            RateLimiter::hit($key, $minutes * 60);
        }

        return $next($request);
    }
}
