<?php

namespace App\Modules\Storefront\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A café's landing page: the look, the words of each section and their order (see LandingSchema).
 *
 * @property string $id
 * @property string $tenant_id
 * @property bool $is_published
 * @property array<string, mixed> $design
 * @property array<string, mixed> $content
 * @property array<int, mixed> $sections
 * @property ?Carbon $published_at
 */
#[Fillable(['is_published', 'design', 'content', 'sections', 'published_at'])]
class StorefrontLanding extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'design' => 'array',
            'content' => 'array',
            'sections' => 'array',
            'published_at' => 'datetime',
        ];
    }
}
