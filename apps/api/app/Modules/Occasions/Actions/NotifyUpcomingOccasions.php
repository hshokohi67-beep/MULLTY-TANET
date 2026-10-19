<?php

namespace App\Modules\Occasions\Actions;

use App\Modules\Core\Support\TenantSettings;
use App\Modules\Identity\Support\PermissionCatalog;
use App\Modules\Notifications\Support\PushNotifier;
use App\Modules\Occasions\Models\TenantOccasion;
use App\Modules\Occasions\Support\UpcomingOccasions;
use App\Support\Localization\PersianNumber;
use Carbon\CarbonImmutable;

/**
 * Tells the current café about occasions that just entered their lead time: once per occurrence
 * (`notified_at`), never for ones it dismissed, and in a respectful tone for mourning days.
 * The bell needs nothing here: it reads the same list live (Insights alerts).
 */
final class NotifyUpcomingOccasions
{
    /** @return int occurrences notified */
    public function handle(?CarbonImmutable $now = null): int
    {
        if (! TenantSettings::get('occasions.notify')) {
            return 0;
        }

        $count = 0;
        foreach (UpcomingOccasions::forCurrentTenant(now: $now) as $o) {
            if (! $o['in_lead'] || $o['status'] !== null) {
                continue;
            }
            $state = TenantOccasion::query()->firstOrCreate(['occasion_date_id' => $o['id']]);
            if ($state->notified_at !== null) {
                continue;
            }

            $when = $o['days_left'] === 0 ? 'از امروز' : PersianNumber::toPersian((string) $o['days_left']).' روز دیگر';
            [$title, $body] = $o['tone'] === 'mourning'
                ? ["یادآوری: {$o['title']}", "{$when}. ساعت کاری، تم و تبلیغات این روزها را بررسی کنید."]
                : ["{$o['title']}: {$when}", 'برای منو و تخفیف این مناسبت ایده‌های آماده داریم.'];

            app(PushNotifier::class)->staffNotice(PermissionCatalog::OCCASIONS_VIEW, $title, $body, '/dashboard/occasions?o='.$o['id'], 'occasion-'.$o['id']);
            $state->update(['notified_at' => now()]);
            $count++;
        }

        return $count;
    }
}
