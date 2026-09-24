<?php

namespace App\Modules\Core\Actions;

use App\Modules\Core\Models\TenantBranding;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores the logo under a random name in the tenant's own prefix. The client
 * filename and extension are never trusted; the extension comes from the sniffed MIME type.
 */
final class UploadTenantLogo
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(UploadedFile $file): TenantBranding
    {
        $disk = Storage::disk(config('filesystems.media_disk'));
        $extension = $file->guessExtension() ?? 'bin';
        $path = sprintf('tenants/%s/branding/%s.%s', $this->context->require()->getKey(), Str::ulid(), $extension);

        $disk->putFileAs(dirname($path), $file, basename($path), ['visibility' => 'public']);

        $branding = TenantBranding::query()->firstOrNew();
        $previous = $branding->logo_path;
        $branding->logo_path = $path;
        $branding->save();

        if ($previous && $previous !== $path) {
            $disk->delete($previous);
        }

        $this->audit->record('branding.logo_uploaded', $branding, ['logo_path' => $path]);

        return $branding;
    }
}
