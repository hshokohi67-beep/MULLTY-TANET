<?php

namespace App\Support\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stamps every request with a correlation id (the caller's own, if it sent one) so a support
 * ticket's id finds every log line for that request, including ones written on error responses.
 */
final class RequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = (string) $request->header('X-Request-Id');
        $id = preg_match('/^[A-Za-z0-9._-]{1,64}$/', $incoming) === 1 ? $incoming : (string) Str::ulid();

        Context::add('request_id', $id);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);

        return $response;
    }
}
