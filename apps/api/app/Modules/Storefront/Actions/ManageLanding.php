<?php

namespace App\Modules\Storefront\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Storefront\Exceptions\LandingException;
use App\Modules\Storefront\Models\StorefrontLanding;
use App\Modules\Storefront\Models\StorefrontMedia;
use App\Modules\Storefront\Support\LandingSchema;
use App\Support\Audit\AuditLogger;
use App\Support\Media\ImageProcessor;
use App\Support\Media\VideoSanitizer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The café's landing page: save its look and words, and manage its photos and hero video.
 * Photos are re-encoded (WebP, EXIF stripped); the video is checked byte-wise and its metadata
 * boxes blanked. A single-file kind (hero photo, hero video, story photo) replaces its old file.
 */
final class ManageLanding
{
    /** Long side of each photo kind (px). */
    private const FIT = ['hero_photo' => 2000, 'story_photo' => 1400, 'gallery' => 1600];

    public function __construct(
        private readonly TenantContext $context,
        private readonly ImageProcessor $images,
        private readonly VideoSanitizer $videos,
        private readonly AuditLogger $audit,
    ) {}

    /** The stored landing page, or an unsaved one with the defaults. */
    public function current(): StorefrontLanding
    {
        return StorefrontLanding::query()->first() ?? new StorefrontLanding([
            'is_published' => false,
            'design' => LandingSchema::defaultDesign(),
            'content' => LandingSchema::defaultContent($this->context->require()->name),
            'sections' => LandingSchema::defaultSections(),
        ]);
    }

    /** @param  array<string, mixed>  $data  validated against LandingSchema::rules() */
    public function save(array $data): StorefrontLanding
    {
        $landing = $this->current();
        $content = LandingSchema::cleanContent($data['content'], $this->context->require()->name);

        // Featured dishes must be this café's own, still on the menu.
        $ids = $content['featured']['product_ids'];
        if ($ids !== [] && Product::query()->whereIn('id', $ids)->count() !== count($ids)) {
            throw LandingException::unknownProducts();
        }

        $publish = (bool) ($data['is_published'] ?? $landing->is_published);
        $landing->fill([
            'design' => LandingSchema::mergeDesign($data['design']),
            'content' => $content,
            'sections' => LandingSchema::mergeSections($data['sections']),
            'is_published' => $publish,
            'published_at' => $publish ? ($landing->published_at ?? now()) : null,
        ])->save();

        $this->audit->record('landing.saved', $landing, ['is_published' => $publish, 'template' => $landing->design['template'] ?? null]);

        return $landing;
    }

    public function addMedia(string $kind, UploadedFile $file, ?string $caption): StorefrontMedia
    {
        $isSingle = in_array($kind, StorefrontMedia::SINGLE, true);
        if (! $isSingle && StorefrontMedia::query()->where('kind', $kind)->count() >= StorefrontMedia::MAX_GALLERY) {
            throw LandingException::galleryFull();
        }

        $stored = $kind === 'hero_video' ? $this->storeVideo($file) : $this->storePhoto($kind, $file);

        $media = DB::transaction(function () use ($kind, $isSingle, $stored, $caption): StorefrontMedia {
            $old = $isSingle ? StorefrontMedia::query()->where('kind', $kind)->lockForUpdate()->get() : collect();
            $media = StorefrontMedia::query()->create([
                ...$stored,
                'kind' => $kind,
                'caption' => $caption,
                'sort' => $isSingle ? 0 : (int) StorefrontMedia::query()->where('kind', $kind)->max('sort') + 1,
            ]);
            $old->each(fn (StorefrontMedia $m) => $this->forget($m));

            return $media;
        });

        $this->audit->record('landing.media_added', $media, ['kind' => $kind, 'bytes' => $media->bytes]);

        return $media;
    }

    public function caption(StorefrontMedia $media, ?string $caption): StorefrontMedia
    {
        $media->update(['caption' => $caption]);

        return $media;
    }

    public function deleteMedia(StorefrontMedia $media): void
    {
        $this->forget($media);
        $this->audit->record('landing.media_deleted', $media, ['kind' => $media->kind]);
    }

    /** @param  list<string>  $ids  gallery order; unknown ids are ignored */
    public function reorder(array $ids): void
    {
        DB::transaction(function () use ($ids): void {
            foreach ($ids as $i => $id) {
                StorefrontMedia::query()->whereKey($id)->where('kind', 'gallery')->update(['sort' => $i]);
            }
        });
    }

    private function forget(StorefrontMedia $media): void
    {
        $media->delete();
        Storage::disk(config('filesystems.media_disk'))->delete(array_filter([$media->path, $media->thumb_path]));
    }

    /** @return array{path: string, thumb_path: ?string, width: int, height: int, bytes: int} */
    private function storePhoto(string $kind, UploadedFile $file): array
    {
        $variants = ['full' => ['fit' => self::FIT[$kind], 'quality' => 80]];
        if ($kind === 'gallery') {
            $variants['thumb'] = ['fit' => 560, 'quality' => 76];
        }

        try {
            $files = $this->images->store($file, $this->context->require()->mediaDirectory('landing'), $variants);
        } catch (RuntimeException) {
            throw LandingException::imageUnreadable();
        }

        return [
            'path' => $files['full']['path'],
            'thumb_path' => $files['thumb']['path'] ?? null,
            'width' => $files['full']['width'],
            'height' => $files['full']['height'],
            'bytes' => (int) Storage::disk(config('filesystems.media_disk'))->size($files['full']['path']),
        ];
    }

    /** @return array{path: string, thumb_path: null, width: null, height: null, bytes: int} */
    private function storeVideo(UploadedFile $file): array
    {
        $tmp = (string) tempnam(sys_get_temp_dir(), 'lv');
        try {
            if (! copy((string) $file->getRealPath(), $tmp)) {
                throw LandingException::videoInvalid();
            }
            $this->videos->sanitize($tmp);
            $path = $this->context->require()->mediaDirectory('landing').'/'.Str::lower((string) Str::ulid()).'.mp4';
            $stream = fopen($tmp, 'rb');
            if ($stream === false) {
                throw LandingException::videoInvalid();
            }
            Storage::disk(config('filesystems.media_disk'))->put($path, $stream, ['visibility' => 'public', 'ContentType' => 'video/mp4']);
            if (is_resource($stream)) {
                fclose($stream);
            }

            return ['path' => $path, 'thumb_path' => null, 'width' => null, 'height' => null, 'bytes' => (int) filesize($tmp)];
        } catch (RuntimeException $e) {
            throw $e instanceof LandingException ? $e : LandingException::videoInvalid();
        } finally {
            @unlink($tmp);
        }
    }
}
