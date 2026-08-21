<?php

namespace App\Http\Middleware;

use App\Http\Controllers\PublicRsvpController;
use App\Models\RsvpResponse;
use App\Services\AuditLogService;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
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
            $this->recordMutationFailureResponse($request, $response);
            $this->auditLog->httpRequest($request, $response->getStatusCode(), $this->duration($startedAt));

            return $response;
        } catch (Throwable $exception) {
            $this->recordMutationFailure($request, $exception);
            $this->auditLog->httpRequest($request, $this->statusCode($exception), $this->duration($startedAt), $exception);

            throw $exception;
        }
    }

    private function recordMutationFailureResponse(Request $request, Response $response): void
    {
        if ($response->getStatusCode() < 400 || $this->mutationFailureWasAudited($request)) {
            return;
        }

        $exception = new HttpException($response->getStatusCode());

        if ($response instanceof JsonResponse && $response->getStatusCode() === 422) {
            $errors = $response->getData(true)['errors'] ?? [];

            if (is_array($errors) && $errors !== []) {
                $exception = ValidationException::withMessages(
                    array_fill_keys(array_keys($errors), ['Validation failed.']),
                );
            }
        }

        $this->recordMutationFailure($request, $exception);
    }

    private function recordMutationFailure(Request $request, Throwable $exception): void
    {
        if ($this->mutationFailureWasAudited($request)) {
            return;
        }

        $actionMethod = $request->route()?->getActionMethod();

        if (! in_array($actionMethod, ['store', 'update', 'destroy'], true)
            || str_starts_with((string) $request->route()?->getName(), 'admin.login')) {
            return;
        }

        $action = ['store' => 'create', 'update' => 'update', 'destroy' => 'delete'][$actionMethod];
        $subject = collect($request->route()?->parameters() ?? [])->first(fn (mixed $value) => $value instanceof Model);
        $controllerClass = (string) $request->route()?->getControllerClass();
        $modelType = $subject?->getMorphClass() ?? $this->modelTypeFromController($controllerClass);
        $validationFields = $exception instanceof ValidationException ? array_keys($exception->errors()) : [];

        $this->auditLog->mutationFailed(
            $request,
            $action,
            $modelType,
            $exception,
            $subject,
            $subject?->attributesToArray(),
            attemptedFields: array_keys($request->all()),
            validationFields: $validationFields,
            stage: $exception instanceof ValidationException ? 'validation' : 'request_pipeline',
        );
    }

    private function mutationFailureWasAudited(Request $request): bool
    {
        return (bool) ($request->attributes->get('mutation_failure_audited')
            || ($request->route()?->getAction()['mutation_failure_audited'] ?? false));
    }

    private function modelTypeFromController(string $controllerClass): string
    {
        if ($controllerClass === PublicRsvpController::class) {
            return RsvpResponse::class;
        }

        $modelClass = 'App\\Models\\'.str_replace('Controller', '', class_basename($controllerClass));

        return is_subclass_of($modelClass, Model::class) ? $modelClass : $controllerClass;
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
