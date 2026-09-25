<?php

use App\Modules\Identity\Http\Middleware\EnsureTenantMember;
use App\Modules\Identity\Http\Middleware\RequireActor;
use App\Support\Entitlements\RequireFeature;
use App\Support\Http\ApiExceptionRenderer;
use App\Support\Http\DomainException;
use App\Support\Http\Middleware\RequestId;
use App\Support\Http\Middleware\SecurityHeaders;
use App\Support\Tenancy\ResolveTenant;
use App\Support\Tenancy\TenantNotResolvedException;
use App\Support\Tenancy\TenantSuspendedException;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind Cloudflare/load balancers: trust only the configured proxy ranges so
        // $request->ip() is the real visitor (rate limits depend on it).
        $trusted = env('TRUSTED_PROXIES');
        $middleware->trustProxies(
            at: $trusted === '*' ? '*' : array_filter(array_map('trim', explode(',', (string) $trusted))),
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'actor' => RequireActor::class,
            'tenant.member' => EnsureTenantMember::class,
            'feature' => RequireFeature::class,
        ]);

        // The tenant must be known before tokens are resolved (customer tokens are tenant-bound).
        $middleware->prependToPriorityList(AuthenticatesRequests::class, ResolveTenant::class);
        // Actor + membership checks run before route-model binding, so non-members can't probe which IDs exist.
        $middleware->appendToPriorityList(AuthenticatesRequests::class, RequireActor::class);
        $middleware->appendToPriorityList(RequireActor::class, EnsureTenantMember::class);
        // Plan features are checked after membership (a stranger gets 403, not an upsell).
        $middleware->appendToPriorityList(EnsureTenantMember::class, RequireFeature::class);

        // First so every log line for this request, including ones from middleware below, carries it.
        $middleware->api(prepend: [RequestId::class], append: [SecurityHeaders::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(new ApiExceptionRenderer);

        // Expected business outcomes are not errors worth reporting.
        $exceptions->dontReport([
            DomainException::class,
            TenantNotResolvedException::class,
            TenantSuspendedException::class,
        ]);
    })->create();
