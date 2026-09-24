<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Data\ProductData;
use App\Modules\Catalog\Data\VariantData;
use App\Modules\Catalog\Models\Product;
use App\Support\Validation\TenantExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create (variants required) or update (variants optional) a product. Prices are integer rial.
 */
final class ProductRequest extends FormRequest
{
    public const MAX_PRICE = 1_000_000_000_000; // 100bn toman: generous, but blocks overflow games

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');

        return [
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            // Overrides the category's menu mood; null = inherit.
            'temperature' => ['sometimes', 'nullable', Rule::in(['hot', 'cold'])],
            'sort' => ['nullable', 'integer', 'between:0,65535'],
            'category_ids' => ['sometimes', 'array', 'max:10'],
            'category_ids.*' => ['string', 'distinct', TenantExists::in('categories')],
            'nutrition' => ['nullable', 'array:calories,caffeine_mg,protein_g'],
            'nutrition.calories' => ['nullable', 'integer', 'between:0,5000'],
            'nutrition.caffeine_mg' => ['nullable', 'integer', 'between:0,1000'],
            'nutrition.protein_g' => ['nullable', 'integer', 'between:0,500'],
            'dietary_tags' => ['nullable', 'array', 'max:10'],
            'dietary_tags.*' => ['string', 'distinct', Rule::in(array_keys(Product::DIETARY_TAGS))],
            ...self::variantRules($creating ? 'required' : 'sometimes'),
        ];
    }

    /** @return array<string, mixed> */
    public static function variantRules(string $presence = 'required'): array
    {
        return [
            'variants' => [$presence, 'array', 'min:1', 'max:10'],
            'variants.*.id' => ['nullable', 'string', TenantExists::in('product_variants')],
            'variants.*.name' => ['nullable', 'string', 'max:80'],
            'variants.*.base_price' => ['required', 'integer', 'min:0', 'max:'.self::MAX_PRICE],
            'variants.*.sku' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'variants.*.is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $v) => self::requireVariantNames($v, (array) $this->input('variants', []))];
    }

    /**
     * With several variants, each needs a name ("کوچک", "بزرگ" = small, large) so customers can tell them apart.
     *
     * @param  array<int, mixed>  $variants
     */
    public static function requireVariantNames(Validator $validator, array $variants): void
    {
        if (count($variants) < 2) {
            return;
        }

        foreach ($variants as $i => $variant) {
            if (trim((string) ($variant['name'] ?? '')) === '') {
                $validator->errors()->add("variants.$i.name", __('validation.variant_name_required'));
            }
        }
    }

    public function toData(): ProductData
    {
        $v = $this->validated();

        return new ProductData(
            name: $v['name'],
            description: $v['description'] ?? null,
            isActive: (bool) ($v['is_active'] ?? true),
            isFeatured: (bool) ($v['is_featured'] ?? false),
            sort: (int) ($v['sort'] ?? 0),
            categoryIds: array_values($v['category_ids'] ?? []),
            nutrition: isset($v['nutrition']) ? array_filter($v['nutrition'], fn ($x) => $x !== null) : null,
            dietaryTags: isset($v['dietary_tags']) ? array_values($v['dietary_tags']) : null,
            temperature: $v['temperature'] ?? null,
        );
    }

    /** @return list<VariantData>|null */
    public function variants(): ?array
    {
        return self::toVariantData($this->validated('variants'));
    }

    /**
     * @param  ?array<int, array<string, mixed>>  $variants
     * @return list<VariantData>|null
     */
    public static function toVariantData(?array $variants): ?array
    {
        if ($variants === null) {
            return null;
        }

        return array_values(array_map(fn (array $v) => new VariantData(
            id: $v['id'] ?? null,
            name: isset($v['name']) && trim($v['name']) !== '' ? trim($v['name']) : null,
            basePrice: (int) $v['base_price'],
            sku: $v['sku'] ?? null,
            isActive: (bool) ($v['is_active'] ?? true),
        ), $variants));
    }
}
