<?php

namespace App\Modules\Storefront\Support;

use App\Modules\Catalog\Models\Product;
use App\Modules\Storefront\Models\StorefrontLanding;
use App\Modules\Storefront\Models\StorefrontMedia;
use Illuminate\Support\Facades\Storage;

/**
 * The landing page as the panel edits it and as the storefront shows it. The public shape holds
 * only what the café wrote or uploaded: no tenant id, no internal paths, and featured product ids
 * filtered to products that are still active.
 */
final class LandingPresenter
{
    /** @return array<string, mixed> */
    public function staff(StorefrontLanding $landing): array
    {
        return [
            'is_published' => $landing->is_published,
            'published_at' => $landing->published_at?->toIso8601String(),
            'design' => LandingSchema::mergeDesign($landing->design),
            'content' => $landing->content,
            'sections' => LandingSchema::mergeSections($landing->sections),
            'media' => $this->media(),
            'options' => ['design' => LandingSchema::DESIGN, 'variants' => LandingSchema::VARIANTS],
        ];
    }

    /** @return array<string, mixed> */
    public function public(StorefrontLanding $landing): array
    {
        $content = $landing->content;
        $ids = $content['featured']['product_ids'] ?? [];
        if ($ids !== []) {
            $active = Product::query()->whereIn('id', $ids)->where('is_active', true)->pluck('id')->all();
            $content['featured']['product_ids'] = array_values(array_intersect($ids, $active));
        }

        return [
            'design' => LandingSchema::mergeDesign($landing->design),
            'content' => $content,
            'sections' => LandingSchema::mergeSections($landing->sections),
            'media' => $this->media(),
        ];
    }

    /** @return array<string, mixed> */
    private function media(): array
    {
        $disk = Storage::disk(config('filesystems.media_disk'));
        $all = StorefrontMedia::query()->orderBy('sort')->orderBy('created_at')->get();
        $one = function (string $kind) use ($all, $disk): ?array {
            $m = $all->firstWhere('kind', $kind);

            return $m instanceof StorefrontMedia ? $this->item($m, $disk->url($m->path), null) : null;
        };

        return [
            'hero_photo' => $one('hero_photo'),
            'hero_video' => $one('hero_video'),
            'story_photo' => $one('story_photo'),
            'gallery' => $all->where('kind', 'gallery')->values()
                ->map(fn (StorefrontMedia $m) => $this->item($m, $disk->url($m->path), $m->thumb_path ? $disk->url($m->thumb_path) : null))
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function item(StorefrontMedia $m, string $url, ?string $thumb): array
    {
        return ['id' => $m->id, 'url' => $url, 'thumb_url' => $thumb ?? $url, 'width' => $m->width, 'height' => $m->height, 'caption' => $m->caption, 'bytes' => $m->bytes];
    }
}
