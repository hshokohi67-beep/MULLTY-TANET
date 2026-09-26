<?php

namespace App\Support\Sms;

/**
 * Messages a café sends to its own customers or owners, through the café's own SMS panel (never
 * the platform line). Lower modules (Loyalty, Insights) depend on this contract; the Messaging
 * module binds the real implementation. Implementations never throw.
 */
interface CafeMessenger
{
    /**
     * An automatic message the café has switched on (TemplateCatalog key), rendered with $values.
     *
     * @param  array<string, string>  $values
     */
    public function template(string $key, string $phoneE164, array $values): void;

    /**
     * A system message with a fixed text (e.g. the owner's end-of-day report).
     *
     * @param  list<string>  $recipients  E.164
     */
    public function send(string $kind, array $recipients, string $text): void;
}
