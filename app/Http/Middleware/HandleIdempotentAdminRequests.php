<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class HandleIdempotentAdminRequests
{
    private const CACHE_SECONDS = 600;

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('POST')) {
            return $next($request);
        }

        $idempotencyKey = (string) $request->header('X-Idempotency-Key');

        if (! preg_match('/^[A-Za-z0-9-]{16,100}$/', $idempotencyKey)) {
            return response()->json([
                'message' => 'A valid idempotency key is required for this request.',
            ], 400);
        }

        $fingerprint = hash('sha256', implode('|', [
            (string) $request->user()->getAuthIdentifier(),
            (string) ($request->route()?->getName() ?? $request->path()),
            $idempotencyKey,
        ]));
        $cacheKey = 'admin-idempotency:'.$fingerprint;
        $payloadHash = hash('sha256', $request->getContent());

        if ($cached = Cache::get($cacheKey)) {
            if (! hash_equals($cached['payload_hash'], $payloadHash)) {
                return $this->conflictResponse();
            }

            return response()
                ->json($cached['data'], $cached['status'])
                ->header('X-Idempotent-Replayed', 'true');
        }

        $lock = Cache::lock($cacheKey.':lock', 10);

        if (! $lock->get()) {
            return response()->json([
                'message' => 'An identical request is already being processed.',
            ], 409);
        }

        try {
            if ($cached = Cache::get($cacheKey)) {
                if (! hash_equals($cached['payload_hash'], $payloadHash)) {
                    return $this->conflictResponse();
                }

                return response()
                    ->json($cached['data'], $cached['status'])
                    ->header('X-Idempotent-Replayed', 'true');
            }

            $response = $next($request);

            if ($response instanceof JsonResponse && $response->isSuccessful()) {
                Cache::put($cacheKey, [
                    'data' => $response->getData(true),
                    'status' => $response->getStatusCode(),
                    'payload_hash' => $payloadHash,
                ], self::CACHE_SECONDS);
            }

            $response->headers->set('X-Idempotency-Key', $idempotencyKey);

            return $response;
        } finally {
            $lock->release();
        }
    }

    private function conflictResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'This idempotency key was already used with different data.',
        ], 409);
    }
}
