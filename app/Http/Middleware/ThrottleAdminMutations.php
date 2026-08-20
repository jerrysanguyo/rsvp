<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleAdminMutations
{
    private const MAX_ATTEMPTS = 60;

    private const DECAY_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $identity = $request->user()?->getAuthIdentifier() ?? $request->ip();
        $key = 'admin-mutations:'.$identity;

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $retryAfter = RateLimiter::availableIn($key);

            return response()->json([
                'message' => 'Too many changes were requested. Please wait before trying again.',
            ], 429, [
                'Retry-After' => $retryAfter,
                'X-RateLimit-Limit' => self::MAX_ATTEMPTS,
                'X-RateLimit-Remaining' => 0,
            ]);
        }

        RateLimiter::hit($key, self::DECAY_SECONDS);
        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit', (string) self::MAX_ATTEMPTS);
        $response->headers->set('X-RateLimit-Remaining', (string) RateLimiter::remaining($key, self::MAX_ATTEMPTS));

        return $response;
    }
}
