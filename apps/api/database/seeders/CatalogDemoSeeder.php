<?php

namespace Database\Seeders;

use App\Modules\Catalog\Actions\SaveCategory;
use App\Modules\Catalog\Actions\SaveModifierGroup;
use App\Modules\Catalog\Actions\SaveProduct;
use App\Modules\Catalog\Data\ProductData;
use App\Modules\Catalog\Data\VariantData;
use App\Modules\Catalog\Models\Product;
use App\Support\Money\CurrencyUnit;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * A realistic Persian cafe menu for local development. Runs inside the tenant given to run().
 * Prices are written in toman here for readability and stored as rial.
 */
class CatalogDemoSeeder extends Seeder
{
    public function run(SaveCategory $saveCategory, SaveProduct $saveProduct, SaveModifierGroup $saveGroup, TenantContext $context): void
    {
        $context->require();
        $t = fn (int $toman) => Money::fromUnit($toman, CurrencyUnit::Toman)->rials;

        $drinks = $saveCategory->handle(['name' => 'نوشیدنی‌ها', 'sort' => 0]);
        $hot = $saveCategory->handle(['name' => 'قهوه‌ی گرم', 'parent_id' => $drinks->id, 'sort' => 0, 'temperature' => 'hot']);
        $cold = $saveCategory->handle(['name' => 'نوشیدنی سرد', 'parent_id' => $drinks->id, 'sort' => 1, 'temperature' => 'cold']);
        $cakes = $saveCategory->handle(['name' => 'کیک و دسر', 'sort' => 1]);
        $breakfast = $saveCategory->handle(['name' => 'صبحانه', 'sort' => 2, 'temperature' => 'hot']);

        $milk = $saveGroup->handle(['name' => 'نوع شیر', 'min_select' => 1, 'max_select' => 1], [
            ['name' => 'شیر معمولی', 'price_delta' => 0, 'is_default' => true],
            ['name' => 'شیر بادام', 'price_delta' => $t(25_000)],
            ['name' => 'شیر جو دوسر', 'price_delta' => $t(25_000)],
        ]);
        $extras = $saveGroup->handle(['name' => 'افزودنی‌ها', 'min_select' => 0, 'max_select' => 0], [
            ['name' => 'شات اضافه اسپرسو', 'price_delta' => $t(20_000)],
            ['name' => 'سیروپ وانیل', 'price_delta' => $t(15_000)],
            ['name' => 'سیروپ کارامل', 'price_delta' => $t(15_000)],
            ['name' => 'خامه', 'price_delta' => $t(10_000)],
        ]);

        $items = [
            ['اسپرسو', 'دوبل، از دانه‌ی عربیکای برشته‌ی تازه', [$hot], [[null, 65_000]], ['calories' => 5, 'caffeine_mg' => 128], ['vegan', 'sugar_free'], [$extras], true],
            ['لاته', 'اسپرسو و شیر بخارداده با کف ملایم', [$hot], [['کوچک', 85_000], ['بزرگ', 105_000]], ['calories' => 190, 'caffeine_mg' => 128], [], [$milk, $extras], true],
            ['کاپوچینو', 'اسپرسو، شیر و کف غلیظ', [$hot], [['کوچک', 85_000], ['بزرگ', 100_000]], ['calories' => 130, 'caffeine_mg' => 128], [], [$milk, $extras], false],
            ['آیس لاته', 'لاته‌ی سرد با یخ', [$cold], [[null, 110_000]], ['calories' => 160, 'caffeine_mg' => 128], [], [$milk, $extras], false],
            ['لیموناد نعناع', 'لیموی تازه، نعناع و سودا', [$cold], [[null, 90_000]], ['calories' => 120], ['vegan'], [], false],
            ['چیزکیک نیویورکی', 'با سس توت‌فرنگی', [$cakes], [[null, 145_000]], ['calories' => 420], [], [], true],
            ['کیک شکلاتی', 'کیک خیس با گاناش تلخ', [$cakes], [[null, 130_000]], ['calories' => 380], ['contains_nuts'], [], false],
            ['املت ایرانی', 'گوجه، تخم‌مرغ، نان سنگک', [$breakfast], [[null, 160_000]], ['calories' => 350, 'protein_g' => 18], ['vegetarian'], [], false],
        ];

        foreach ($items as $i => [$name, $description, $categories, $variants, $nutrition, $tags, $groups, $featured]) {
            $product = $saveProduct->handle(
                new ProductData(
                    name: $name,
                    description: $description,
                    isFeatured: $featured,
                    sort: $i,
                    categoryIds: array_map(fn ($c) => $c->id, $categories),
                    nutrition: $nutrition,
                    dietaryTags: $tags ?: null,
                ),
                null,
                array_map(fn (array $v) => new VariantData(null, $v[0], $t($v[1])), $variants),
            );

            $product->modifierGroups()->sync(collect($groups)->mapWithKeys(fn ($g, $sort) => [$g->id => ['tenant_id' => $product->tenant_id, 'sort' => $sort]])->all());
        }

        // Make sure the version stamp reflects the pivot writes above.
        Product::query()->first()?->touch();
    }
}
