<?php

namespace App\Modules\Marketplace\Actions;

use App\Modules\Marketplace\Models\MarketplacePlaceImage;
use App\Support\Audit\AuditLogger;
use App\Support\Media\ImageProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** City tile photos for «خوراک‌گردی»: re-encoded like any upload; the old files are removed. */
final class ManagePlaceImages
{
    public function __construct(
        private readonly ImageProcessor $images,
        private readonly AuditLogger $audit,
    ) {}

    public function upload(string $city, UploadedFile $file): MarketplacePlaceImage
    {
        try {
            $stored = $this->images->store($file, 'places', [
                'wide' => ['fit' => 1200, 'quality' => 82],
                'small' => ['fit' => 600, 'quality' => 80],
            ]);
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['image' => 'این تصویر قابل پردازش نیست. یک عکس JPG، PNG یا WebP دیگر انتخاب کنید.']);
        }

        $image = MarketplacePlaceImage::query()->firstOrNew(['city' => $city]);
        $previous = $image->exists ? [$image->image_path, $image->image_small_path] : [];
        $image->fill(['image_path' => $stored['wide']['path'], 'image_small_path' => $stored['small']['path']])->save();
        Storage::disk(config('filesystems.media_disk'))->delete($previous);
        $this->audit->record('platform.place_image_uploaded', $image, ['city' => $city]);

        return $image;
    }

    public function remove(string $city): void
    {
        $image = MarketplacePlaceImage::query()->where('city', $city)->first();
        if ($image === null) {
            return;
        }
        Storage::disk(config('filesystems.media_disk'))->delete([$image->image_path, $image->image_small_path]);
        $image->delete();
        $this->audit->record('platform.place_image_removed', null, ['city' => $city]);
    }
}
