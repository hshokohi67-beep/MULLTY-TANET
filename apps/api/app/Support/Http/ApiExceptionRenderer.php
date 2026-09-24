<?php

namespace App\Support\Http;

use App\Support\Tenancy\TenantNotResolvedException;
use App\Support\Tenancy\TenantSuspendedException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Turns every API exception into {"message": <Persian>, "code": <stable id>, "errors"?: {...}}.
 * Framework/English messages ("Unauthorized", "ValidationException", …) never reach clients.
 */
final class ApiExceptionRenderer
{
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! ($request->is('api/*') || $request->expectsJson())) {
            return null;
        }

        return match (true) {
            $e instanceof DomainException => $this->json($e->getMessage(), $e->errorCode, $e->status),
            $e instanceof ValidationException => $this->json(__('messages.errors.validation'), 'validation_failed', 422, ['errors' => $e->errors()]),
            $e instanceof AuthenticationException => $this->json(__('messages.errors.unauthenticated'), 'unauthenticated', 401),
            $e instanceof AuthorizationException, $e instanceof AccessDeniedHttpException => $this->json(__('messages.errors.forbidden'), 'forbidden', 403),
            $e instanceof TenantNotResolvedException => $this->json(__('messages.errors.tenant_not_found'), 'tenant_not_found', 404),
            $e instanceof TenantSuspendedException => $this->json(__('messages.errors.tenant_suspended'), 'tenant_suspended', 403),
            $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => $this->json(__('messages.errors.not_found'), 'not_found', 404),
            $e instanceof ThrottleRequestsException, $e instanceof TooManyRequestsHttpException => $this->json(__('messages.errors.throttled'), 'too_many_requests', 429, headers: $e->getHeaders()),
            $e instanceof MethodNotAllowedHttpException => $this->json(__('messages.errors.method_not_allowed'), 'method_not_allowed', 405),
            $e instanceof HttpExceptionInterface => $this->json(__('messages.errors.generic'), 'http_'.$e->getStatusCode(), $e->getStatusCode(), headers: $e->getHeaders()),
            // Unexpected errors: keep the debug page locally, a generic Persian message everywhere else.
            config('app.debug') => null,
            default => $this->json(__('messages.errors.server'), 'server_error', 500),
        };
    }

    /**
     * @param  array<string, mixed>  $extra
     * @param  array<string, string>  $headers
     */
    private function json(string $message, string $code, int $status, array $extra = [], array $headers = []): JsonResponse
    {
        return new JsonResponse(['message' => $message, 'code' => $code, ...$extra], $status, $headers);
    }
}
