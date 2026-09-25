<?php

namespace App\Modules\Marketplace\Models;

use App\Support\Database\StoresDatesInUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A photo the platform designed for a city tile (platform-level).
 *
 * @property string $id
 * @property string $city
 * @property string $image_path
 * @property string $image_small_path
 */
#[Fillable(['city', 'image_path', 'image_small_path'])]
class MarketplacePlaceImage extends Model
{
    use HasUlids, StoresDatesInUtc;

    public function url(): string
    {
        return Storage::disk(config('filesystems.media_disk'))->url($this->image_small_path);
    }

    public function wideUrl(): string
    {
        return Storage::disk(config('filesystems.media_disk'))->url($this->image_path);
    }

    /** @return array<string, string> city => small image URL */
    public static function urls(): array
    {
        $out = [];
        foreach (self::query()->get() as $image) {
            $out[$image->city] = $image->url();
        }

        return $out;
    }
}
