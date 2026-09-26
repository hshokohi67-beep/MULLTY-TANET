<?php

namespace App\Modules\Messaging\Support;

use App\Modules\Messaging\Models\SmsTemplate;

/** The automatic messages a café can switch on and word itself. */
final class TemplateCatalog
{
    /** @var array<string, array{label: string, hint: string, body: string, placeholders: list<string>}> */
    public const TEMPLATES = [
        'order_ready' => [
            'label' => 'سفارش آماده است',
            'hint' => 'وقتی سفارش بیرون‌بر، پیشخوان یا پیش‌سفارش آماده‌ی تحویل شد.',
            'body' => '{name} عزیز، سفارش شماره‌ی {number} شما در {cafe} آماده است.',
            'placeholders' => ['name', 'number', 'cafe'],
        ],
        'order_sent' => [
            'label' => 'سفارش ارسال شد',
            'hint' => 'وقتی سفارش پیک از کافه بیرون رفت.',
            'body' => '{name} عزیز، سفارش شماره‌ی {number} از {cafe} ارسال شد و به‌زودی می‌رسد.',
            'placeholders' => ['name', 'number', 'cafe'],
        ],
        'birthday' => [
            'label' => 'تبریک تولد',
            'hint' => 'همراه هدیه‌ی تولد باشگاه، ساعت ۹ صبح روز تولد.',
            'body' => '{name} عزیز، تولدت مبارک! {cafe} برایت {gift} هدیه گذاشته است.',
            'placeholders' => ['name', 'cafe', 'gift'],
        ],
    ];

    /** @var array<string, string> */
    public const PLACEHOLDER_LABELS = ['name' => 'نام مشتری', 'number' => 'شماره‌ی سفارش', 'cafe' => 'نام کافه', 'gift' => 'هدیه'];

    /** The café's enabled wording for a key, or null when it is switched off (never set = off). */
    public static function enabledBody(string $key): ?string
    {
        $template = SmsTemplate::query()->where('key', $key)->first();

        return $template?->is_enabled ? $template->body : null;
    }
}
