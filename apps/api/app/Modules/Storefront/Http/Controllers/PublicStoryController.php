<?php

namespace App\Modules\Storefront\Http\Controllers;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Models\Branch;
use App\Modules\Storefront\Models\Story;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Live stories for the storefront ring, plus view/click counting. Counting is per visitor IP per
 * story per day (cache), so refreshing or replaying never inflates the numbers.
 */
final class PublicStoryController
{
    public function index(Request $request): JsonResponse
    {
        $slug = $request->validate(['branch' => ['nullable', 'string', 'max:64']])['branch'] ?? null;
        $branchId = $slug ? Branch::query()->where('slug', $slug)->where('is_active', true)->value('id') : null;

        $stories = Story::query()->live()
            ->where(fn ($q) => $q->whereNull('branch_id')->when($branchId, fn ($q) => $q->orWhere('branch_id', $branchId)))
            ->orderBy('sort')->orderByDesc('starts_at')
            ->limit(20)
            ->get();

        // Resolve links now, so a story never points at a product that was switched off.
        $productIds = $stories->where('link_type', 'product')->pluck('link_target')->filter()->all();
        $products = Product::query()->where('is_active', true)->whereIn('id', $productIds)->pluck('slug', 'id');
        $categoryIds = $stories->where('link_type', 'category')->pluck('link_target')->filter()->all();
        $categories = Category::query()->where('is_active', true)->whereIn('id', $categoryIds)->pluck('id')->flip();
        $disk = Storage::disk(config('filesystems.media_disk'));

        $data = $stories->map(function (Story $s) use ($products, $categories, $disk): array {
            $link = match ($s->link_type) {
                'product' => isset($products[$s->link_target]) ? ['type' => 'product', 'slug' => $products[$s->link_target]] : null,
                'category' => isset($categories[$s->link_target]) ? ['type' => 'category', 'category_id' => $s->link_target] : null,
                'url' => ['type' => 'url', 'url' => $s->link_target],
                default => null,
            };

            return [
                'id' => $s->id,
                'image_url' => $disk->url($s->image_path),
                'thumb_url' => $disk->url($s->thumb_path),
                'width' => $s->width,
                'height' => $s->height,
                'caption' => $s->caption,
                'link' => $link,
                'cta_label' => $link ? ($s->cta_label ?: 'مشاهده') : null,
                'starts_at' => $s->starts_at->toIso8601String(),
            ];
        })->values();

        return response()->json(['data' => $data])->header('Cache-Control', 'public, max-age=30, stale-while-revalidate=60');
    }

    public function seen(Request $request, string $storyId): Response
    {
        return $this->count($request, $storyId, 'views');
    }

    public function click(Request $request, string $storyId): Response
    {
        return $this->count($request, $storyId, 'clicks');
    }

    private function count(Request $request, string $storyId, string $column): Response
    {
        $story = Story::query()->live()->find($storyId) ?? throw new NotFoundHttpException;
        $key = sprintf('story:%s:%s:%s:%s', $column, $story->id, sha1((string) $request->ip()), now()->toDateString());

        if (Cache::add($key, 1, now()->addDay())) {
            Story::query()->whereKey($story->id)->increment($column);
        }

        return response()->noContent();
    }
}
