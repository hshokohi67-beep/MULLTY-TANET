<?php

namespace Tests\Feature\Marketplace;

use App\Modules\Advertising\Models\AdPlacement;
use App\Modules\Identity\Models\User;
use App\Modules\Marketplace\Actions\ProjectStore;
use App\Modules\Marketplace\Models\MarketplaceListing;
use App\Modules\Marketplace\Models\MarketplacePlaceImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Commerce\CommerceTestCase;

/** City tile photos designed by the platform, and the «خوراک‌گردی» naming. */
final class PlaceImagesTest extends CommerceTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.media_disk'));
        $this->inTenant($this->tenant, function (): void {
            $this->branch->update(['city' => 'تهران', 'province' => 'تهران']);
            MarketplaceListing::query()->create(['is_listed' => true, 'categories' => ['cafe'], 'amenities' => [], 'price_level' => 1, 'listed_at' => now()]);
            app(ProjectStore::class)->handle();
        });
    }

    public function test_the_platform_designs_a_photo_for_a_city_tile(): void
    {
        $admin = $this->staffHeaders(User::factory()->platformAdmin()->create());
        $home = fn () => $this->getJson('/api/v1/public/marketplace/home', ['Accept' => 'application/json'])->assertOk()->json('data.places.0.cities.0');
        $this->assertNull($home()['image_url']);

        $this->getJson('/api/v1/platform/marketplace/places', $admin)->assertOk()
            ->assertJsonPath('data.0.city', 'تهران')->assertJsonPath('data.0.stores', 1)->assertJsonPath('data.0.image_url', null);

        // Only cities that have stores; only real, large enough images.
        $this->post('/api/v1/platform/marketplace/places/image', ['city' => 'یزد', 'image' => UploadedFile::fake()->image('y.jpg', 1200, 800)], $admin)->assertStatus(422)->assertJsonValidationErrors('city');
        $this->post('/api/v1/platform/marketplace/places/image', ['city' => 'تهران', 'image' => UploadedFile::fake()->image('t.jpg', 200, 100)], $admin)->assertStatus(422)->assertJsonValidationErrors('image');

        $first = $this->post('/api/v1/platform/marketplace/places/image', ['city' => 'تهران', 'image' => UploadedFile::fake()->image('t.jpg', 1200, 800)], $admin)->assertOk()->json('data.image_url');
        $this->assertStringEndsWith('.webp', (string) $first);
        $this->assertSame($first, $home()['image_url']);

        // Replacing removes the old files.
        $old = MarketplacePlaceImage::query()->sole();
        $this->post('/api/v1/platform/marketplace/places/image', ['city' => 'تهران', 'image' => UploadedFile::fake()->image('t2.png', 1000, 700)], $admin)->assertOk();
        Storage::disk(config('filesystems.media_disk'))->assertMissing($old->image_path);
        $this->assertNotSame($first, $home()['image_url']);

        $this->deleteJson('/api/v1/platform/marketplace/places/image?city='.urlencode('تهران'), [], $admin)->assertOk();
        $this->assertNull($home()['image_url']);
        $this->assertSame(0, MarketplacePlaceImage::query()->count());
    }

    public function test_only_the_platform_manages_city_photos(): void
    {
        $staff = $this->staffHeaders($this->owner, $this->tenant);
        $this->getJson('/api/v1/platform/marketplace/places', $staff)->assertForbidden();
        $this->post('/api/v1/platform/marketplace/places/image', ['city' => 'تهران', 'image' => UploadedFile::fake()->image('t.jpg', 1200, 800)], $staff)->assertForbidden();
        $this->deleteJson('/api/v1/platform/marketplace/places/image?city=x', [], $staff)->assertForbidden();
        $this->post('/api/v1/platform/marketplace/places/image', ['city' => 'تهران'], ['Accept' => 'application/json'])->assertUnauthorized();
    }

    public function test_the_marketplace_is_called_khorakgardi(): void
    {
        $this->assertSame('بنر صفحه‌ی اول خوراک‌گردی', AdPlacement::query()->where('key', 'home_banner')->value('name'));
        $this->assertStringNotContainsString('کافه‌گردی', (string) AdPlacement::query()->pluck('description')->implode(' '));
    }
}
