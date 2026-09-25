<?php

namespace App\Support\Entitlements;

use App\Support\Http\DomainException;

final class FeatureNotInPlanException extends DomainException
{
    public function __construct(public readonly string $feature, string $label)
    {
        parent::__construct("«{$label}» در پلن فعلی شما نیست. برای استفاده، پلن را ارتقا دهید.", 'feature_not_in_plan', 402);
    }
}
