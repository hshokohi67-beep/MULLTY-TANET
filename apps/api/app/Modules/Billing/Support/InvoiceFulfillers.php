<?php

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Contracts\InvoiceFulfiller;
use LogicException;

/** Invoice kind => the fulfiller of what it sells. Subscription kinds are handled by Billing itself. */
final class InvoiceFulfillers
{
    /** @var array<string, class-string<InvoiceFulfiller>> */
    private array $map = [];

    /** @param  class-string<InvoiceFulfiller>  $fulfiller */
    public function register(string $kind, string $fulfiller): void
    {
        $this->map[$kind] = $fulfiller;
    }

    public function for(string $kind): InvoiceFulfiller
    {
        $class = $this->map[$kind] ?? throw new LogicException("No fulfiller registered for invoice kind [{$kind}].");

        return app($class);
    }
}
