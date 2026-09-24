<?php

namespace Database\Seeders;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Storefront\Actions\ManageStories;
use App\Modules\Storefront\Models\Story;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

/**
 * Three demo stories with generated artwork (soft gradients with bokeh), so the storefront story
 * ring has something to show without shipping stock photos. Idempotent.
 */
class StorefrontDemoSeeder extends Seeder
{
    public function run(ManageStories $stories, TenantContext $context): void
    {
        $context->require();

        if (Story::query()->exists()) {
            return;
        }

        $latte = Product::query()->where('name', 'لاته')->first();
        $cold = Category::query()->where('name', 'نوشیدنی سرد')->first();

        $items = [
            ['colors' => [[180, 83, 9], [124, 45, 18]], 'data' => ['caption' => 'لاته‌ی پاییزی با دارچین رسید؛ گرم، نرم و خوش‌عطر', 'link_type' => $latte ? 'product' : 'none', 'link_target' => $latte?->id, 'cta_label' => 'سفارش لاته']],
            ['colors' => [[3, 105, 161], [12, 74, 110]], 'data' => ['caption' => 'هنوز هوا گرمه؟ نوشیدنی‌های خنک ما رو امتحان کن', 'link_type' => $cold ? 'category' : 'none', 'link_target' => $cold?->id, 'cta_label' => 'دیدن نوشیدنی‌های سرد']],
            ['colors' => [[15, 118, 110], [19, 78, 74]], 'data' => ['caption' => 'هر ۱۰ هزار تومان خرید = امتیاز باشگاه؛ با شماره موبایل عضو شوید', 'link_type' => 'none']],
        ];

        foreach ($items as $i => $item) {
            $stories->save([...$item['data'], 'starts_at' => now()->subMinutes(30 - $i)->toIso8601String()], $this->artwork($item['colors'], $i));
        }
    }

    /** @param  array{0: array{int, int, int}, 1: array{int, int, int}}  $colors */
    private function artwork(array $colors, int $seed): UploadedFile
    {
        [$w, $h] = [900, 1600];
        $img = imagecreatetruecolor($w, $h);
        [$top, $bottom] = $colors;

        for ($y = 0; $y < $h; $y++) {
            $t = $y / $h;
            $c = imagecolorallocate($img, ...array_map(fn ($a, $b) => (int) ($a + ($b - $a) * $t), $top, $bottom));
            imageline($img, 0, $y, $w, $y, (int) $c);
        }

        mt_srand(42 + $seed);
        imagealphablending($img, true);
        for ($i = 0; $i < 14; $i++) {
            $r = mt_rand(60, 260);
            $c = imagecolorallocatealpha($img, 255, 255, 255, mt_rand(100, 118));
            imagefilledellipse($img, mt_rand(0, $w), mt_rand(0, $h), $r, $r, (int) $c);
        }

        $path = tempnam(sys_get_temp_dir(), 'story').'.png';
        imagepng($img, $path);
        imagedestroy($img);

        return new UploadedFile($path, 'story.png', 'image/png', null, true);
    }
}
