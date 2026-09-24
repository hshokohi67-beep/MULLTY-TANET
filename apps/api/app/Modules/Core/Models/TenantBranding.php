<?php

namespace App\Modules\Core\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property ?string $logo_path
 * @property ?string $cover_path
 * @property string $primary_color
 * @property string $theme
 * @property ?string $seo_title
 * @property ?string $seo_description
 */
#[Table('tenant_branding')]
#[Fillable(['logo_path', 'cover_path', 'primary_color', 'theme', 'seo_title', 'seo_description'])]
class TenantBranding extends Model
{
    use BelongsToTenant, HasUlids;
}
