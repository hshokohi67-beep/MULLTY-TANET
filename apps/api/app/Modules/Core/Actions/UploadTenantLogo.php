<?php

namespace App\Modules\Core\Actions;

use App\Modules\Core\Models\TenantBranding;
use App\Support\Audit\AuditLogger;
use App\Support\Media\ImageProcessor;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Re-encoded to WebP (≤ 512 px, metadata stripped) like every other public photo; the old file is removed. */
final class UploadTenantLogo
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly ImageProcessor $images,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(UploadedFile $file): TenantBranding
    {
        try {
            $stored = $this->images->store($file, sprintf('tenants/%s/branding', $this->context->require()->getKey()), [
                'logo' => ['fit' => 512, 'quality' => 85],
            ]);
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['logo' => 'این تصویر قابل پردازش نیست. یک عکس JPG، PNG یا WebP دیگر انتخاب کنید.']);
        }

        $branding = TenantBranding::query()->firstOrNew();
        $previous = $branding->logo_path;
        $branding->logo_path = $stored['logo']['path'];
        $branding->save();

        if ($previous) {
            Storage::disk(config('filesystems.media_disk'))->delete($previous);
        }

        $this->audit->record('branding.logo_uploaded', $branding, ['logo_path' => $branding->logo_path]);

        return $branding;
    }
}
