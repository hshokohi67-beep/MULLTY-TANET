<?php

namespace App\Modules\Notifications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** A browser's PushSubscription (endpoint + keys), plus where a tap should go (a local path). */
final class PushSubscriptionRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'string', 'url:https', 'max:1000'],
            'keys' => ['required', 'array:p256dh,auth'],
            'keys.p256dh' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9_-]+={0,2}$/'],
            'keys.auth' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+={0,2}$/'],
            'url' => ['nullable', 'string', 'max:400', 'regex:#^/(?!/)[^\s\x5c]*$#'],
        ];
    }

    /** @return array{endpoint: string, p256dh: string, auth: string} */
    public function keys(): array
    {
        return [
            'endpoint' => (string) $this->validated('endpoint'),
            'p256dh' => rtrim((string) $this->validated('keys.p256dh'), '='),
            'auth' => rtrim((string) $this->validated('keys.auth'), '='),
        ];
    }
}
