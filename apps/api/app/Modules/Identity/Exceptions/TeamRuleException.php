<?php

namespace App\Modules\Identity\Exceptions;

use App\Support\Http\DomainException;

final class TeamRuleException extends DomainException
{
    public static function lastOwner(): self
    {
        return new self('کسب‌وکار باید دست‌کم یک مالک فعال داشته باشد.', 'last_owner', 422);
    }

    public static function onlyOwnerAssignsOwner(): self
    {
        return new self('فقط مالک کسب‌وکار می‌تواند نقش «مالک» را واگذار یا حذف کند.', 'owner_only', 403);
    }

    public static function alreadyMember(): self
    {
        return new self('این کاربر از قبل عضو تیم است.', 'already_member', 422);
    }
}
