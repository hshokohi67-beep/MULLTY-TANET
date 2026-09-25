<?php

namespace Tests\Feature\Storefront;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Support\CatalogVersion;
use App\Modules\Storefront\Models\Story;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Commerce\CommerceTestCase;

final class StoriesTest extends CommerceTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /** @param  array<string, mixed>  $fields */
    private function create(array $fields = [], ?UploadedFile $image = null): TestResponse
    {
        return $this->post('/api/v1/stories', ['image' => $image ?? UploadedFile::fake()->image('story.jpg', 1800, 2400), ...$fields], [
            ...$this->staffHeaders($this->owner, $this->tenant), 'Accept' => 'application/json',
        ]);
    }

    public function test_upload_is_re_encoded_to_webp_with_a_square_thumbnail_and_24h_default(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-25 10:00:00', 'UTC'));

        $story = $this->create(['caption' => 'لاته‌ی پاییزی رسید', 'link_type' => 'product', 'link_target' => $this->latte->id, 'cta_label' => 'سفارش بده'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'live')
            ->assertJsonPath('data.width', 1200)   // long side fitted to 1600
            ->assertJsonPath('data.height', 1600)
            ->assertJsonPath('data.ends_at', '2026-09-26T10:00:00+00:00')
            ->json('data');

        $model = $this->inTenant($this->tenant, fn () => Story::query()->findOrFail($story['id']));
        $disk = Storage::disk('public');
        $this->assertStringEndsWith('.webp', $model->image_path);
        $this->assertStringStartsWith("t/{$this->tenant->refresh()->media_key}/stories/", $model->image_path);
        $this->assertSame('image/webp', getimagesizefromstring((string) $disk->get($model->image_path))['mime']);
        $thumb = getimagesizefromstring((string) $disk->get($model->thumb_path));
        $this->assertSame([240, 240], [$thumb[0], $thumb[1]]);
    }

    public function test_links_must_point_inside_the_tenant_or_to_https(): void
    {
        $this->create(['link_type' => 'url', 'link_target' => 'http://example.com'])->assertUnprocessable()->assertJsonValidationErrors('link_target');
        $this->create(['link_type' => 'url', 'link_target' => 'javascript:alert(1)'])->assertUnprocessable()->assertJsonValidationErrors('link_target');
        $this->create(['link_type' => 'product', 'link_target' => '01ARZ3NDEKTSV4RRFFQ69G5FAV'])->assertUnprocessable()->assertJsonValidationErrors('link_target');
        $this->create(['link_type' => 'url', 'link_target' => 'https://instagram.com/cafe'])->assertCreated();
        $this->create([], UploadedFile::fake()->image('tiny.jpg', 200, 200))->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->create([], UploadedFile::fake()->create('evil.svg', 10, 'image/svg+xml'))->assertUnprocessable()->assertJsonValidationErrors('image');
    }

    public function test_public_list_shows_only_live_stories_with_resolved_links(): void
    {
        $category = $this->inTenant($this->tenant, fn () => Category::query()->create(['name' => 'پاییزی', 'slug' => 'autumn']));
        $live = $this->create(['link_type' => 'product', 'link_target' => $this->latte->id])->json('data.id');
        $this->create(['link_type' => 'category', 'link_target' => $category->id]);
        $this->create(['starts_at' => now()->addDay()->toIso8601String(), 'ends_at' => now()->addDays(2)->toIso8601String()]); // scheduled
        $this->create(['is_active' => '0']); // off
        $offProduct = $this->create(['link_type' => 'product', 'link_target' => $this->espresso->id])->json('data.id');
        $this->inTenant($this->tenant, fn () => Product::query()->whereKey($this->espresso->id)->update(['is_active' => false]));

        $data = $this->getJson('/api/v1/public/stories', $this->publicHeaders())->assertOk()->assertJsonCount(3, 'data')->json('data');
        $byId = collect($data)->keyBy('id');
        $this->assertSame(['type' => 'product', 'slug' => $this->latte->slug], $byId[$live]['link']);
        $this->assertSame('مشاهده', $byId[$live]['cta_label']);
        $this->assertNull($byId[$offProduct]['link']); // the product was switched off
        $this->assertArrayNotHasKey('views', $data[0]);

        // After 24 hours the first ones expire and the scheduled one goes live; a day later it ends too.
        $this->travel(25)->hours();
        $this->getJson('/api/v1/public/stories', $this->publicHeaders())->assertOk()->assertJsonCount(1, 'data');
        $this->travel(24)->hours();
        $this->getJson('/api/v1/public/stories', $this->publicHeaders())->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/stories', $this->staffHeaders($this->owner, $this->tenant))->assertOk()->assertJsonPath('data.0.status', 'expired');
    }

    public function test_views_and_clicks_are_counted_once_per_visitor_per_day(): void
    {
        $id = $this->create()->json('data.id');

        $this->postJson("/api/v1/public/stories/{$id}/seen", [], $this->publicHeaders())->assertNoContent();
        $this->postJson("/api/v1/public/stories/{$id}/seen", [], $this->publicHeaders())->assertNoContent();
        $this->postJson("/api/v1/public/stories/{$id}/click", [], $this->publicHeaders())->assertNoContent();
        $this->postJson("/api/v1/public/stories/{$id}/seen", [], $this->publicHeaders())->assertNoContent();
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])->postJson("/api/v1/public/stories/{$id}/seen", [], $this->publicHeaders())->assertNoContent();

        $this->getJson('/api/v1/stories', $this->staffHeaders($this->owner, $this->tenant))
            ->assertJsonPath('data.0.views', 2)
            ->assertJsonPath('data.0.clicks', 1);

        $this->postJson('/api/v1/public/stories/01ARZ3NDEKTSV4RRFFQ69G5FAV/seen', [], $this->publicHeaders())->assertNotFound();
    }

    public function test_edit_reorder_delete_and_permissions(): void
    {
        $a = $this->create(['caption' => 'اول'])->json('data');
        $b = $this->create(['caption' => 'دوم'])->json('data');
        $headers = [...$this->staffHeaders($this->owner, $this->tenant), 'Accept' => 'application/json'];

        // New image replaces the old files.
        $oldPath = $this->inTenant($this->tenant, fn () => Story::query()->findOrFail($a['id'])->image_path);
        $this->post("/api/v1/stories/{$a['id']}", ['image' => UploadedFile::fake()->image('n.png', 800, 800), 'caption' => 'ویرایش شد'], $headers)
            ->assertOk()->assertJsonPath('data.caption', 'ویرایش شد')->assertJsonPath('data.width', 800);
        Storage::disk('public')->assertMissing($oldPath);

        $this->putJson('/api/v1/stories/order', ['ids' => [$b['id'], $a['id']]], $headers)->assertNoContent();
        $this->getJson('/api/v1/stories', $headers)->assertJsonPath('data.0.id', $b['id']);

        $paths = $this->inTenant($this->tenant, fn () => Story::query()->findOrFail($b['id'])->only(['image_path', 'thumb_path']));
        $this->deleteJson("/api/v1/stories/{$b['id']}", [], $headers)->assertNoContent();
        Storage::disk('public')->assertMissing(array_values($paths));

        // A cashier cannot manage stories.
        $cashier = $this->addMember($this->tenant, $this->owner, 'cashier');
        $this->getJson('/api/v1/stories', $this->staffHeaders($cashier, $this->tenant))->assertForbidden();
    }

    public function test_menu_carries_the_hot_cold_mood_with_product_override(): void
    {
        $headers = $this->staffHeaders($this->owner, $this->tenant);
        $hot = $this->postJson('/api/v1/catalog/categories', ['name' => 'نوشیدنی گرم', 'temperature' => 'hot'], $headers)->assertCreated()->assertJsonPath('data.temperature', 'hot')->json('data.id');
        $this->postJson('/api/v1/catalog/categories', ['name' => 'بد', 'temperature' => 'warm'], $headers)->assertUnprocessable();

        $this->inTenant($this->tenant, function () use ($hot): void {
            $this->latte->categories()->sync([$hot => ['tenant_id' => $this->tenant->id]]);
            $this->espresso->categories()->sync([$hot => ['tenant_id' => $this->tenant->id]]);
            $this->espresso->update(['temperature' => 'cold']); // e.g. an espresso tonic
        });
        $this->inTenant($this->tenant, fn () => CatalogVersion::bump());

        $menu = collect($this->getJson('/api/v1/public/menu', $this->publicHeaders())->assertOk()->json('data.products'))->keyBy('slug');
        $this->assertSame('hot', $menu[$this->latte->slug]['temperature']);
        $this->assertSame('cold', $menu[$this->espresso->slug]['temperature']);
    }

    public function test_category_image_is_a_square_webp_shown_in_the_menu(): void
    {
        $headers = [...$this->staffHeaders($this->owner, $this->tenant), 'Accept' => 'application/json'];
        $id = $this->postJson('/api/v1/catalog/categories', ['name' => 'قهوه', 'temperature' => 'hot'], $headers)->json('data.id');
        $this->inTenant($this->tenant, fn () => $this->latte->categories()->sync([$id => ['tenant_id' => $this->tenant->id]]));

        $url = $this->post("/api/v1/catalog/categories/{$id}/image", ['image' => UploadedFile::fake()->image('c.jpg', 900, 600)], $headers)
            ->assertOk()->json('data.image_url');
        $path = $this->inTenant($this->tenant, fn () => Category::query()->findOrFail($id)->image_path);
        $size = getimagesizefromstring((string) Storage::disk('public')->get($path));
        $this->assertSame([320, 320, 'image/webp'], [$size[0], $size[1], $size['mime']]);

        $menuCategory = collect($this->getJson('/api/v1/public/menu', $this->publicHeaders())->json('data.categories'))->firstWhere('id', $id);
        $this->assertSame($url, $menuCategory['image_url']);

        $this->post("/api/v1/catalog/categories/{$id}/image", ['image' => UploadedFile::fake()->image('tiny.jpg', 50, 50)], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->deleteJson("/api/v1/catalog/categories/{$id}/image", [], $headers)->assertOk()->assertJsonPath('data.image_url', null);
        Storage::disk('public')->assertMissing($path);
    }
}
