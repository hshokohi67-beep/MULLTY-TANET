<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\CatalogRuleException;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores images under tenants/{tenant}/products/{product}/{ulid}.{sniffed-ext}. The client filename is never used.
 */
final class ManageProductImages
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
    ) {}

    public function upload(Product $product, UploadedFile $file, ?string $alt = null): ProductImage
    {
        return DB::transaction(function () use ($product, $file, $alt): ProductImage {
            $count = ProductImage::query()->where('product_id', $product->getKey())->lockForUpdate()->count();

            if ($count >= ProductImage::MAX_PER_PRODUCT) {
                throw CatalogRuleException::tooManyImages(ProductImage::MAX_PER_PRODUCT);
            }

            $dimensions = @getimagesize($file->getRealPath()) ?: [null, null];
            $directory = sprintf('tenants/%s/products/%s', $this->context->require()->getKey(), $product->getKey());
            $name = Str::ulid().'.'.($file->guessExtension() ?? 'bin');

            Storage::disk(config('filesystems.media_disk'))->putFileAs($directory, $file, $name, ['visibility' => 'public']);

            $image = ProductImage::query()->create([
                'product_id' => $product->getKey(),
                'path' => $directory.'/'.$name,
                'alt' => $alt ?: $product->name,
                'width' => $dimensions[0],
                'height' => $dimensions[1],
                'sort' => $count,
            ]);

            $this->audit->record('product.image_added', $product, ['image_id' => $image->getKey()]);

            return $image;
        });
    }

    public function delete(Product $product, ProductImage $image): void
    {
        DB::transaction(function () use ($product, $image): void {
            $image->delete();
            Storage::disk(config('filesystems.media_disk'))->delete($image->path);

            // Close the gap so sort 0 is always the cover image.
            ProductImage::query()->where('product_id', $product->getKey())->orderBy('sort')->get()
                ->each(fn (ProductImage $img, int $i) => $img->sort === $i ? null : $img->update(['sort' => $i]));

            $this->audit->record('product.image_removed', $product, ['image_id' => $image->getKey()]);
        });
    }
}
