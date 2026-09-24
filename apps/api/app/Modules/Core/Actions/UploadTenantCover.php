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

/** The storefront hero image: re-encoded to WebP (≤ 1920 px, metadata stripped); the old file is removed. */
final class UploadTenantCover
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
                'cover' => ['fit' => 1920, 'quality' => 80],
            ]);
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['cover' => 'این تصویر قابل پردازش نیست. یک عکس JPG، PNG یا WebP دیگر انتخاب کنید.']);
        }

        $branding = TenantBranding::query()->firstOrNew();
        $previous = $branding->cover_path;
        $branding->cover_path = $stored['cover']['path'];
        $branding->save();

        if ($previous) {
            Storage::disk(config('filesystems.media_disk'))->delete($previous);
        }

        $this->audit->record('branding.cover_uploaded', $branding, ['cover_path' => $branding->cover_path]);

        return $branding;
    }

    public function remove(): TenantBranding
    {
        $branding = TenantBranding::query()->firstOrNew();

        if ($branding->cover_path) {
            Storage::disk(config('filesystems.media_disk'))->delete($branding->cover_path);
            $branding->cover_path = null;
            $branding->save();
            $this->audit->record('branding.cover_removed', $branding, []);
        }

        return $branding;
    }
}
