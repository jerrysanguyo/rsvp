<?php

use App\Http\Middleware\AuditHttpRequests;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleIdempotentAdminRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\ThrottleAdminMutations;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'idempotent.admin' => HandleIdempotentAdminRequests::class,
            'secure.mutations' => ThrottleAdminMutations::class,
        ]);

        $middleware->append(AuditHttpRequests::class);
        $middleware->append(SecurityHeaders::class);

        $middleware->redirectGuestsTo(fn (Request $request) => route('admin.login'));
        $middleware->redirectUsersTo(fn (Request $request) => route('admin.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response): Response {
            if ($requestId = request()->attributes->get('audit_request_id')) {
                $response->headers->set('X-Request-ID', $requestId);
            }

            return $response;
        });
    })->create();
