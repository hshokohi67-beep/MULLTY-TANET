<?php

namespace Database\Seeders;

use App\Modules\Catalog\Models\Modifier;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Models\Branch;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\ManagePurchases;
use App\Modules\Inventory\Actions\ManageRecipes;
use App\Modules\Inventory\Actions\ManageStock;
use App\Modules\Inventory\Models\Ingredient;
use App\Modules\Inventory\Models\Supplier;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Demo stock: a few ingredients, recipes for the coffee drinks (with the almond-milk swap),
 * a supplier and a received purchase order, so the inventory screens and food-cost figures have
 * something real to show. Idempotent.
 */
class InventoryDemoSeeder extends Seeder
{
    public function run(ManageStock $stock, ManageRecipes $recipes, ManagePurchases $purchases, TenantContext $context): void
    {
        $context->require();
        if (Ingredient::query()->exists()) {
            return;
        }

        $t = fn (int $toman) => $toman * 10;
        $make = fn (string $name, string $unit, int $costToman, float $threshold = 0, ?string $pack = null, ?float $packSize = null) => $stock->saveIngredient([
            'name' => $name, 'unit' => $unit, 'cost_per_big_unit' => $t($costToman), 'low_stock_threshold' => $threshold, 'pack_label' => $pack, 'pack_size' => $packSize,
        ]);

        $beans = $make('قهوه‌ی عربیکا', 'g', 1_800_000, 1000);
        $milk = $make('شیر', 'ml', 45_000, 3000, 'پاکت ۱ لیتری', 1000);
        $almond = $make('شیر بادام', 'ml', 160_000, 1000);
        $oat = $make('شیر جو دوسر', 'ml', 150_000, 1000);
        $cup = $make('لیوان کاغذی', 'pcs', 2_500, 100);
        $syrup = $make('سیروپ وانیل', 'ml', 350_000, 200);
        $lemon = $make('لیمو', 'pcs', 3_000, 10);

        $variants = fn (string $name) => Product::query()->where('name', $name)->with('variants')->first()?->variants->sortBy('sort')->values();
        $modifier = fn (string $name) => Modifier::query()->where('name', $name)->value('id');
        $coffee = fn (int $milkMl) => [['ingredient_id' => $beans->id, 'quantity' => 18], ['ingredient_id' => $milk->id, 'quantity' => $milkMl], ['ingredient_id' => $cup->id, 'quantity' => 1]];

        $mods = array_values(array_filter([
            $modifier('شیر بادام') ? ['modifier_id' => $modifier('شیر بادام'), 'items' => [['ingredient_id' => $almond->id, 'quantity' => 200], ['ingredient_id' => $milk->id, 'quantity' => -200]]] : null,
            $modifier('شیر جو دوسر') ? ['modifier_id' => $modifier('شیر جو دوسر'), 'items' => [['ingredient_id' => $oat->id, 'quantity' => 200], ['ingredient_id' => $milk->id, 'quantity' => -200]]] : null,
            $modifier('شات اضافه اسپرسو') ? ['modifier_id' => $modifier('شات اضافه اسپرسو'), 'items' => [['ingredient_id' => $beans->id, 'quantity' => 9]]] : null,
            $modifier('سیروپ وانیل') ? ['modifier_id' => $modifier('سیروپ وانیل'), 'items' => [['ingredient_id' => $syrup->id, 'quantity' => 15]]] : null,
        ]));

        foreach (['لاته' => [200, 300], 'کاپوچینو' => [150, 220], 'آیس لاته' => [220]] as $name => $milkBySize) {
            $vs = $variants($name);
            $product = Product::query()->where('name', $name)->first();
            if (! $vs || ! $product) {
                continue;
            }
            $recipes->save($product, $vs->map(fn ($v, $i) => ['variant_id' => $v->id, 'items' => $coffee($milkBySize[$i] ?? $milkBySize[0])])->all(), $mods);
        }
        if (($espresso = Product::query()->where('name', 'اسپرسو')->first()) && ($vs = $variants('اسپرسو'))) {
            $recipes->save($espresso, [['variant_id' => $vs[0]->id, 'items' => [['ingredient_id' => $beans->id, 'quantity' => 18], ['ingredient_id' => $cup->id, 'quantity' => 1]]]], $mods);
        }
        if (($lemonade = Product::query()->where('name', 'لیموناد نعناع')->first()) && ($vs = $variants('لیموناد نعناع'))) {
            $recipes->save($lemonade, [['variant_id' => $vs[0]->id, 'items' => [['ingredient_id' => $lemon->id, 'quantity' => 2], ['ingredient_id' => $cup->id, 'quantity' => 1]]]], []);
        }

        $owner = User::query()->orderBy('created_at')->value('id') ?? '';
        $branch = Branch::query()->where('slug', 'main')->value('id') ?? Branch::query()->value('id');
        $supplier = Supplier::query()->create(['name' => 'پخش قهوه‌ی آفتاب', 'phone' => '+989121112233', 'notes' => 'تحویل شنبه و سه‌شنبه']);
        $dairy = Supplier::query()->create(['name' => 'لبنیات کاله']);

        $po = $purchases->save(['supplier_id' => $supplier->id, 'branch_id' => $branch, 'items' => [
            ['ingredient_id' => $beans->id, 'quantity' => 3, 'unit' => 'kg', 'unit_price' => $t(1_900_000)],
            ['ingredient_id' => $cup->id, 'quantity' => 250, 'unit' => 'pcs', 'unit_price' => $t(2_600)],
            ['ingredient_id' => $syrup->id, 'quantity' => 1, 'unit' => 'l', 'unit_price' => $t(350_000)],
            ['ingredient_id' => $lemon->id, 'quantity' => 30, 'unit' => 'pcs', 'unit_price' => $t(3_000)],
        ]], null, $owner);
        $purchases->markOrdered($po);
        $purchases->receive($po, null, $owner, 'demo-1');
        $purchases->pay($po, $t(4_000_000), 'transfer', null, $owner);

        $milkPo = $purchases->save(['supplier_id' => $dairy->id, 'branch_id' => $branch, 'items' => [
            ['ingredient_id' => $milk->id, 'quantity' => 12, 'unit' => 'pack', 'unit_price' => $t(48_000)],
            ['ingredient_id' => $almond->id, 'quantity' => 2, 'unit' => 'l', 'unit_price' => $t(160_000)],
            ['ingredient_id' => $oat->id, 'quantity' => 0.8, 'unit' => 'l', 'unit_price' => $t(150_000)],
        ]], null, $owner);
        $purchases->receive($milkPo, null, $owner, 'demo-2');

        // One open order waiting for delivery.
        $purchases->markOrdered($purchases->save(['supplier_id' => $dairy->id, 'branch_id' => $branch, 'items' => [
            ['ingredient_id' => $milk->id, 'quantity' => 24, 'unit' => 'pack', 'unit_price' => $t(48_000)],
        ]], null, $owner));
    }
}
