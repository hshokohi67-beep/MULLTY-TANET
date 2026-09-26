<?php

namespace App\Support\Push;

/** What happened to one push: sent, the subscription is gone (delete it), failed, or skipped (push off). */
final class PushResult
{
    private function __construct(
        public readonly string $outcome,
        public readonly int $status,
    ) {}

    public static function sent(): self
    {
        return new self('sent', 201);
    }

    public static function gone(int $status): self
    {
        return new self('gone', $status);
    }

    public static function failed(int $status): self
    {
        return new self('failed', $status);
    }

    public static function skipped(): self
    {
        return new self('skipped', 0);
    }
}
