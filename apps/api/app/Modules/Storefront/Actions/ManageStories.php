<?php

namespace App\Modules\Storefront\Actions;

use App\Modules\Storefront\Exceptions\StoryException;
use App\Modules\Storefront\Models\Story;
use App\Support\Audit\AuditLogger;
use App\Support\Media\ImageProcessor;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Create, edit, reorder and delete stories. Every photo is re-encoded (WebP, EXIF stripped) into
 * a full image (long side ≤ 1600 px) and a 240 px square thumbnail for the story ring.
 */
final class ManageStories
{
    /** Stories that are scheduled or live at the same time. */
    public const MAX_QUEUED = 30;

    public function __construct(
        private readonly TenantContext $context,
        private readonly ImageProcessor $images,
        private readonly AuditLogger $audit,
    ) {}

    /** @param  array<string, mixed>  $data */
    public function save(array $data, ?UploadedFile $image, ?Story $story = null): Story
    {
        $story ??= new Story;
        $isNew = ! $story->exists;

        if ($isNew && Story::query()->where('ends_at', '>', now())->count() >= self::MAX_QUEUED) {
            throw StoryException::tooMany();
        }

        $old = null;
        if ($image !== null) {
            $old = $story->exists ? [$story->image_path, $story->thumb_path] : null;
            $files = $this->process($image);
            $story->fill([
                'image_path' => $files['full']['path'],
                'thumb_path' => $files['thumb']['path'],
                'width' => $files['full']['width'],
                'height' => $files['full']['height'],
            ]);
        }

        $starts = array_key_exists('starts_at', $data) && $data['starts_at'] ? CarbonImmutable::parse($data['starts_at']) : ($story->starts_at ?? now());
        $type = $data['link_type'] ?? $story->link_type ?? 'none';

        $story->fill([
            'branch_id' => array_key_exists('branch_id', $data) ? $data['branch_id'] : $story->branch_id,
            'caption' => array_key_exists('caption', $data) ? $data['caption'] : $story->caption,
            'link_type' => $type,
            'link_target' => $type === 'none' ? null : ($data['link_target'] ?? $story->link_target),
            'cta_label' => array_key_exists('cta_label', $data) ? $data['cta_label'] : $story->cta_label,
            'starts_at' => $starts,
            'ends_at' => array_key_exists('ends_at', $data) && $data['ends_at'] ? CarbonImmutable::parse($data['ends_at']) : ($story->ends_at ?? CarbonImmutable::instance($starts)->addHours(Story::DEFAULT_HOURS)),
            'is_active' => $data['is_active'] ?? $story->is_active ?? true,
        ]);

        if ($isNew) {
            $story->sort = (int) Story::query()->max('sort') + 1;
        }

        $story->save();

        if ($old !== null) {
            Storage::disk(config('filesystems.media_disk'))->delete($old);
        }

        $this->audit->record($isNew ? 'story.created' : 'story.updated', $story, ['link_type' => $story->link_type]);

        return $story;
    }

    /** @param  list<string>  $ids  the new order; unknown ids are ignored */
    public function reorder(array $ids): void
    {
        DB::transaction(function () use ($ids): void {
            foreach ($ids as $i => $id) {
                Story::query()->whereKey($id)->update(['sort' => $i]);
            }
        });
    }

    public function delete(Story $story): void
    {
        $story->delete();
        Storage::disk(config('filesystems.media_disk'))->delete([$story->image_path, $story->thumb_path]);
        $this->audit->record('story.deleted', $story, []);
    }

    /** @return array<string, array{path: string, width: int, height: int}> */
    private function process(UploadedFile $image): array
    {
        try {
            return $this->images->store($image, $this->context->require()->mediaDirectory('stories'), [
                'full' => ['fit' => 1600, 'quality' => 80],
                'thumb' => ['fit' => 240, 'square' => true, 'quality' => 78],
            ]);
        } catch (RuntimeException) {
            throw StoryException::imageUnreadable();
        }
    }
}
