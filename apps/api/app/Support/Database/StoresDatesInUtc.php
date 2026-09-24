<?php

namespace App\Support\Database;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent formats a Carbon value using *its own* timezone when saving, so
 * "2026-09-25T09:30+03:30" would be stored as 09:30 and read back as 09:30 UTC
 * (3.5 hours off). The platform rule is "the database stores UTC": convert first.
 *
 * @mixin Model
 */
trait StoresDatesInUtc
{
    /**
     * @param  mixed  $value
     * @return string|null
     */
    public function fromDateTime($value)
    {
        return empty($value) ? $value : $this->asDateTime($value)->utc()->format($this->getDateFormat());
    }
}
