<?php

namespace App\Http\Middleware;

use App\Services\AuditLogService;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class AuditHttpRequests
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $requestId = $this->requestId($request);
        $request->attributes->set('audit_request_id', $requestId);

        try {
            $response = $next($request);
            $response->headers->set('X-Request-ID', $requestId);
            $this->auditLog->httpRequest($request, $response->getStatusCode(), $this->duration($startedAt));

            return $response;
        } catch (Throwable $exception) {
            $this->auditLog->httpRequest($request, $this->statusCode($exception), $this->duration($startedAt), $exception);

            throw $exception;
        }
    }

    private function requestId(Request $request): string
    {
        $provided = (string) $request->header('X-Request-ID');

        return Str::isUuid($provided) ? $provided : (string) Str::uuid();
    }

    private function duration(int $startedAt): float
    {
        return (hrtime(true) - $startedAt) / 1_000_000;
    }

    private function statusCode(Throwable $exception): int
    {
        return match (true) {
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            $exception instanceof HttpResponseException => $exception->getResponse()->getStatusCode(),
            $exception instanceof ValidationException => $exception->status,
            default => 500,
        };
    }
}
