<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Category;
use App\Support\Audit\AuditLogger;
use App\Support\Media\ImageProcessor;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * The round picture on a category chip in the online menu: centre-cropped to a 320 px square
 * WebP (metadata stripped). Replacing or removing it deletes the old file.
 */
final class ManageCategoryImage
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly ImageProcessor $images,
        private readonly AuditLogger $audit,
    ) {}

    public function upload(Category $category, UploadedFile $file): Category
    {
        try {
            $stored = $this->images->store($file, sprintf('tenants/%s/categories', $this->context->require()->getKey()), [
                'square' => ['fit' => 320, 'square' => true, 'quality' => 82],
            ]);
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['image' => 'این تصویر قابل پردازش نیست. یک عکس JPG، PNG یا WebP دیگر انتخاب کنید.']);
        }

        $previous = $category->image_path;
        $category->image_path = $stored['square']['path'];
        $category->save();

        if ($previous) {
            Storage::disk(config('filesystems.media_disk'))->delete($previous);
        }

        $this->audit->record('category.image_uploaded', $category, ['image_path' => $category->image_path]);

        return $category;
    }

    public function remove(Category $category): Category
    {
        if ($category->image_path) {
            Storage::disk(config('filesystems.media_disk'))->delete($category->image_path);
            $category->image_path = null;
            $category->save();
            $this->audit->record('category.image_removed', $category, []);
        }

        return $category;
    }
}
