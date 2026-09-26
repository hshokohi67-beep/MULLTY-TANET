<?php

namespace Tests\Feature\Storefront;

use App\Modules\Catalog\Models\Product;
use App\Modules\Storefront\Models\StorefrontMedia;
use App\Modules\Storefront\Support\LandingSchema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Commerce\CommerceTestCase;

final class LandingTest extends CommerceTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /** @return array<string, string> */
    private function h(): array
    {
        return [...$this->staffHeaders($this->owner, $this->tenant), 'Accept' => 'application/json'];
    }

    /** @return array<string, mixed> */
    private function page(array $override = []): array
    {
        $data = $this->getJson('/api/v1/storefront/landing', $this->h())->assertOk()->json('data');

        return array_replace_recursive([
            'is_published' => true,
            'design' => $data['design'],
            'content' => $data['content'],
            'sections' => $data['sections'],
        ], $override);
    }

    private function save(array $page): TestResponse
    {
        return $this->putJson('/api/v1/storefront/landing', $page, $this->h());
    }

    private function upload(string $kind, UploadedFile $file): TestResponse
    {
        return $this->post('/api/v1/storefront/landing/media', ['kind' => $kind, 'file' => $file], $this->h());
    }

    /** A tiny but well-formed MP4 whose metadata box carries a GPS position, like a phone's. */
    private function mp4(): string
    {
        $box = fn (string $type, string $body) => pack('N', 8 + strlen($body)).$type.$body;
        $udta = $box('udta', $box("\xA9xyz", '+35.7000+051.4000/'));

        return $box('ftyp', 'isom'.pack('N', 512).'isomiso2mp41')
            .$box('moov', $box('mvhd', str_repeat("\0", 100)).$udta)
            .$box('mdat', str_repeat("\x11", 64));
    }

    public function test_defaults_until_saved_then_public_after_publishing(): void
    {
        $this->getJson('/api/v1/public/landing', $this->publicHeaders())->assertNotFound();

        $this->getJson('/api/v1/storefront/landing', $this->h())->assertOk()
            ->assertJsonPath('data.is_published', false)
            ->assertJsonPath('data.design', LandingSchema::defaultDesign())
            ->assertJsonPath('data.content.hero.title', $this->tenant->name)
            ->assertJsonPath('data.sections.0', ['key' => 'story', 'visible' => true]);

        $page = $this->page([
            'design' => ['template' => 'warm', 'hero' => 'split', 'font' => 'samim', 'texture' => 'dots'],
            'content' => [
                'hero' => ['title' => '  قهوه، آرام و دقیق  ', 'subtitle' => ''],
                'highlights' => ['items' => [['value' => '۱۰۰', 'unit' => '٪', 'label' => 'دانه‌ی تازه‌برشت']]],
                'featured' => ['product_ids' => [$this->latte->id, $this->espresso->id]],
                'marquee' => ['phrases' => ['قهوه‌ی تازه', ' ', 'کیک خانگی']],
            ],
        ]);
        $page['sections'] = array_reverse($page['sections']);

        $this->save($page)->assertOk()
            ->assertJsonPath('data.design.template', 'warm')
            ->assertJsonPath('data.content.hero.title', 'قهوه، آرام و دقیق')
            ->assertJsonPath('data.content.hero.subtitle', null)
            ->assertJsonPath('data.content.marquee.phrases', ['قهوه‌ی تازه', 'کیک خانگی'])
            ->assertJsonPath('data.sections.0.key', 'visit');

        // A dish switched off disappears from the public page; tenant ids never leave the API.
        $this->inTenant($this->tenant, fn () => Product::query()->whereKey($this->espresso->id)->update(['is_active' => false]));
        $public = $this->getJson('/api/v1/public/landing', $this->publicHeaders())->assertOk()
            ->assertJsonPath('data.design.hero', 'split')
            ->assertJsonPath('data.content.featured.product_ids', [$this->latte->id])
            ->assertJsonMissingPath('data.is_published')
            ->json();
        $this->assertStringNotContainsString($this->tenant->id, (string) json_encode($public));

        $this->save([...$page, 'is_published' => false])->assertOk();
        $this->getJson('/api/v1/public/landing', $this->publicHeaders())->assertNotFound();
    }

    public function test_only_known_options_and_own_products_are_accepted(): void
    {
        $this->save($this->page(['design' => ['template' => 'neon']]))->assertUnprocessable()->assertJsonValidationErrors('design.template');
        $this->save($this->page(['content' => ['featured' => ['variant' => 'wall']]]))->assertUnprocessable()->assertJsonValidationErrors('content.featured.variant');
        $this->save($this->page(['content' => ['hero' => ['title' => str_repeat('ق', 81)]]]))->assertUnprocessable()->assertJsonValidationErrors('content.hero.title');
        $this->save($this->page(['content' => ['highlights' => ['items' => array_fill(0, 5, ['value' => '1', 'unit' => null, 'label' => 'x'])]]]))
            ->assertUnprocessable()->assertJsonValidationErrors('content.highlights.items');
        $this->save($this->page(['content' => ['hero' => ['script' => '<b>']]]))->assertUnprocessable();

        $page = $this->page();
        $page['sections'][1]['key'] = $page['sections'][0]['key'];
        $this->save($page)->assertUnprocessable()->assertJsonValidationErrors('sections.1.key');

        $this->save($this->page(['content' => ['featured' => ['product_ids' => ['01ARZ3NDEKTSV4RRFFQ69G5FAV']]]]))
            ->assertUnprocessable()->assertJsonPath('code', 'landing_unknown_products');
    }

    public function test_photos_are_re_encoded_and_single_kinds_replace_their_old_file(): void
    {
        $first = $this->upload('hero_photo', UploadedFile::fake()->image('hero.jpg', 3000, 2000))->assertCreated()->json('data.media.hero_photo');
        $this->assertSame([2000, 1333], [$first['width'], $first['height']]);

        $old = $this->inTenant($this->tenant, fn () => StorefrontMedia::query()->where('kind', 'hero_photo')->sole()->path);
        $this->assertStringStartsWith("t/{$this->tenant->refresh()->media_key}/landing/", $old);
        $this->assertSame('image/webp', getimagesizefromstring((string) Storage::disk('public')->get($old))['mime']);

        $this->upload('hero_photo', UploadedFile::fake()->image('hero2.png', 1600, 900))->assertCreated();
        Storage::disk('public')->assertMissing($old);
        $this->assertSame(1, $this->inTenant($this->tenant, fn () => StorefrontMedia::query()->where('kind', 'hero_photo')->count()));

        $this->upload('gallery', UploadedFile::fake()->create('evil.svg', 10, 'image/svg+xml'))->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->upload('gallery', UploadedFile::fake()->image('tiny.jpg', 300, 300))->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_gallery_is_capped_ordered_and_captioned(): void
    {
        // Eleven already there (uploads are rate-limited to 10 a minute), then the twelfth and one too many.
        $ids = $this->inTenant($this->tenant, fn () => collect(range(0, StorefrontMedia::MAX_GALLERY - 2))->map(fn (int $i) => StorefrontMedia::query()->create([
            'kind' => 'gallery', 'path' => "t/x/landing/g{$i}.webp", 'thumb_path' => "t/x/landing/g{$i}-thumb.webp", 'width' => 800, 'height' => 600, 'bytes' => 1000, 'sort' => $i,
        ])->id)->all());
        $ids[] = $this->upload('gallery', UploadedFile::fake()->image('g11.jpg', 800, 600))->assertCreated()->json('data.media.gallery.11.id');
        $this->upload('gallery', UploadedFile::fake()->image('extra.jpg', 800, 600))->assertUnprocessable()->assertJsonPath('code', 'landing_gallery_full');

        $this->putJson('/api/v1/storefront/landing/media/order', ['ids' => array_reverse($ids)], $this->h())->assertNoContent();
        $this->patchJson("/api/v1/storefront/landing/media/{$ids[0]}", ['caption' => ' میز کنار پنجره '], $this->h())->assertNoContent();

        $gallery = $this->getJson('/api/v1/storefront/landing', $this->h())->json('data.media.gallery');
        $this->assertSame($ids[11], $gallery[0]['id']);
        $this->assertSame('میز کنار پنجره', $gallery[11]['caption']);
        $this->assertNotSame($gallery[0]['url'], $gallery[0]['thumb_url']);

        $this->deleteJson("/api/v1/storefront/landing/media/{$ids[0]}", [], $this->h())->assertNoContent();
        $this->assertCount(11, $this->getJson('/api/v1/storefront/landing', $this->h())->json('data.media.gallery'));
    }

    public function test_video_must_be_a_real_mp4_and_loses_its_metadata(): void
    {
        $this->upload('hero_video', UploadedFile::fake()->createWithContent('clip.mp4', $this->mp4()))->assertCreated()
            ->assertJsonPath('data.media.hero_video.width', null);

        $path = $this->inTenant($this->tenant, fn () => StorefrontMedia::query()->where('kind', 'hero_video')->sole()->path);
        $stored = (string) Storage::disk('public')->get($path);
        $this->assertStringEndsWith('.mp4', $path);
        $this->assertSame(strlen($this->mp4()), strlen($stored)); // nothing moved
        $this->assertStringNotContainsString('udta', $stored);
        $this->assertStringContainsString('moov', $stored);

        // A renamed text file, a truncated MP4, or anything over 8 MB is refused.
        $this->upload('hero_video', UploadedFile::fake()->createWithContent('clip.mp4', "hello\n"))->assertUnprocessable();
        $this->upload('hero_video', UploadedFile::fake()->createWithContent('cut.mp4', substr($this->mp4(), 0, 40)))->assertUnprocessable();
        $this->upload('hero_video', UploadedFile::fake()->create('big.mp4', 8 * 1024 + 1, 'video/mp4'))->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_cashiers_cannot_edit_the_landing_page(): void
    {
        $cashier = $this->addMember($this->tenant, $this->owner, 'cashier');
        $this->getJson('/api/v1/storefront/landing', $this->staffHeaders($cashier, $this->tenant))->assertForbidden();
    }
}
