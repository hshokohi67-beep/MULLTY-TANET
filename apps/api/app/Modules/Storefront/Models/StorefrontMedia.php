<?php

namespace App\Modules\Storefront\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A photo (WebP, re-encoded) or the hero video of a landing page.
 *
 * @property string $id
 * @property string $kind hero_photo|hero_video|story_photo|gallery
 * @property string $path
 * @property ?string $thumb_path
 * @property ?int $width
 * @property ?int $height
 * @property int $bytes
 * @property ?string $caption
 * @property int $sort
 */
#[Fillable(['kind', 'path', 'thumb_path', 'width', 'height', 'bytes', 'caption', 'sort'])]
class StorefrontMedia extends Model
{
    use BelongsToTenant, HasUlids;

    protected $table = 'storefront_media';

    public const KINDS = ['hero_photo', 'hero_video', 'story_photo', 'gallery'];

    /** Kinds that hold a single file (a new upload replaces the old one). */
    public const SINGLE = ['hero_photo', 'hero_video', 'story_photo'];

    public const MAX_GALLERY = 12;

    protected function casts(): array
    {
        return ['width' => 'integer', 'height' => 'integer', 'bytes' => 'integer', 'sort' => 'integer'];
    }
}
