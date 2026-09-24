<?php

namespace App\Support\Tenancy;

use Illuminate\Support\ServiceProvider;

final class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped: one instance per request / queued job, flushed between them (also safe under Octane).
        $this->app->scoped(TenantContext::class);
    }
}
