<?php

namespace Database\Seeders;

use App\Modules\Advertising\Models\AdCampaign;
use App\Modules\Advertising\Models\AdDailyStat;
use App\Modules\Advertising\Models\AdPlacement;
use App\Modules\Advertising\Support\AdPricing;
use App\Modules\Core\Models\Tenant;
use App\Support\Media\ImageProcessor;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Demo ads: a few running banners and sponsored results, one campaign waiting for review and one
 * approved and awaiting payment, plus two weeks of counters. Banner art is generated (abstract
 * gradients), so no external images are needed. Dev/demo only; idempotent (by campaign name).
 */
class AdsDemoSeeder extends Seeder
{
    /** slug, name, placement, status, headline, body, cta, days since start (negative = future), length, colours */
    private const CAMPAIGNS = [
        ['narenj', 'هفته‌ی دمی', 'home_banner', 'paid', 'هفته‌ی قهوه‌ی دمی نارنج', 'هر دمی دوم نصف قیمت؛ تا آخر ماه', 'offer', 2, 14, [[251, 146, 60], [194, 65, 12]]],
        ['eram', 'عصرهای باغ', 'home_banner', 'paid', 'فالوده‌ی شیرازی زیر نارنج‌ها', 'عصرهای پنجشنبه با موسیقی زنده', 'visit', 1, 10, [[20, 184, 166], [15, 94, 89]]],
        ['cafe-nemooneh', 'صبحانه‌ی آخر هفته', 'home_banner', 'paid', 'صبحانه‌ی آخر هفته در کافه نمونه', 'پنکیک، املت و قهوه‌ی دمی؛ سفارش آنلاین', 'order', 5, 12, [[190, 18, 60], [88, 28, 135]]],
        ['koohpayeh', 'صبحانه تا ظهر', 'search_top', 'paid', 'صبحانه‌ی کوهستانی تا ظهر', 'با منظره‌ی دربند', 'menu', 1, 7, null],
        ['cafe-nemooneh', 'قهوه‌ی تخصصی', 'search_top', 'approved', 'اسپرسوی تازه‌برشته هر روز', 'دانه‌های تک‌خاستگاه', 'menu', -1, 5, null],
        ['yas', 'بستنی تابستانه', 'search_top', 'pending', 'بستنی زعفرانی دست‌ساز', 'از ۱۳۵۲ در چهارباغ', 'visit', -2, 7, null],
    ];

    public function run(TenantContext $context, ImageProcessor $images): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('AdsDemoSeeder must not run in production.');
        }

        foreach (self::CAMPAIGNS as $row) {
            $this->campaign($context, $images, ...$row);
        }
    }

    /** @param  ?array{array{int, int, int}, array{int, int, int}}  $colours */
    private function campaign(TenantContext $context, ImageProcessor $images, string $slug, string $name, string $placement, string $status, string $headline, string $body, string $cta, int $since, int $days, ?array $colours): void
    {
        $tenant = Tenant::query()->where('slug', $slug)->first();
        if ($tenant === null) {
            return;
        }
        $context->runAs($tenant, function () use ($tenant, $images, $name, $placement, $status, $headline, $body, $cta, $since, $days, $colours): void {
            if (AdCampaign::query()->where('name', $name)->exists()) {
                return;
            }
            $price = (int) AdPlacement::query()->where('key', $placement)->value('daily_price');
            $start = CarbonImmutable::now($tenant->timezone)->subDays($since)->toDateString();
            [$startsAt, $endsAt] = AdPricing::window($start, $days, $tenant->timezone);
            $ref = Str::random(16);
            $paths = $colours === null ? [null, null] : $this->art($images, $ref, $colours[0], $colours[1]);
            $ago = max($since, 0);

            $campaign = AdCampaign::query()->forceCreate([
                'ref' => $ref, 'name' => $name, 'placement' => $placement, 'status' => $status, 'start_date' => $start, 'days' => $days,
                'starts_at' => $startsAt, 'ends_at' => $endsAt, 'cities' => [], 'headline' => $headline, 'body' => $body, 'cta' => $cta,
                'image_path' => $paths[0], 'image_small_path' => $paths[1], 'daily_price' => $price, 'amount' => $price * $days,
                'submitted_at' => now()->subDays($ago + 2), 'reviewed_at' => $status === 'pending' ? null : now()->subDays($ago + 1),
                'paid_at' => $status === 'paid' ? now()->subDays($ago + 1) : null,
            ]);

            // Counters since the start (at most two weeks) for running campaigns.
            if ($status === 'paid') {
                for ($d = 0; $d <= min($ago, 14); $d++) {
                    $impressions = random_int(180, 520);
                    $stat = AdDailyStat::query()->create(['campaign_id' => $campaign->id, 'day' => CarbonImmutable::now($tenant->timezone)->subDays($d)->toDateString()]);
                    $stat->forceFill(['impressions' => $impressions, 'clicks' => (int) round($impressions * random_int(18, 55) / 1000)])->save();
                }
            }
        });
    }

    /**
     * Abstract banner art: a diagonal gradient with soft light circles, re-encoded like any upload.
     *
     * @param  array{int, int, int}  $from
     * @param  array{int, int, int}  $to
     * @return array{string, string}
     */
    private function art(ImageProcessor $images, string $ref, array $from, array $to): array
    {
        $w = 1600;
        $h = 600;
        $img = imagecreatetruecolor($w, $h);
        for ($x = 0; $x < $w; $x++) {
            $t = $x / $w;
            $colour = (int) imagecolorallocate($img, (int) ($from[0] + ($to[0] - $from[0]) * $t), (int) ($from[1] + ($to[1] - $from[1]) * $t), (int) ($from[2] + ($to[2] - $from[2]) * $t));
            imageline($img, $x, 0, $x, $h, $colour);
        }
        imagealphablending($img, true);
        mt_srand(crc32($ref));
        for ($i = 0; $i < 14; $i++) {
            $size = mt_rand(80, 420);
            $light = (int) imagecolorallocatealpha($img, 255, 255, 255, mt_rand(96, 118));
            imagefilledellipse($img, mt_rand(0, $w), mt_rand(-100, $h + 100), $size, $size, $light);
        }
        $path = tempnam(sys_get_temp_dir(), 'ad').'.png';
        imagepng($img, $path);
        imagedestroy($img);

        $stored = $images->store(new UploadedFile($path, 'banner.png', 'image/png', null, true), 'ads/'.$ref, [
            'wide' => ['fit' => 1600, 'quality' => 82],
            'small' => ['fit' => 800, 'quality' => 80],
        ]);
        @unlink($path);

        return [$stored['wide']['path'], $stored['small']['path']];
    }
}
