<?php

namespace App\Support\Entitlements;

use App\Support\Http\DomainException;

final class PlanLimitReachedException extends DomainException
{
    public function __construct(public readonly string $feature, string $label, int $limit)
    {
        $n = strtr((string) $limit, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
        parent::__construct("سقف «{$label}» در پلن شما {$n} است. برای افزودن بیشتر، پلن را ارتقا دهید یا افزونه بگیرید.", 'plan_limit_reached', 402);
    }
}
