<?php

namespace App\Modules\Insights\Support;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductAvailability;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Support\OrderFilters;
use App\Modules\Customers\Models\Customer;
use App\Modules\Loyalty\Support\CustomerDirectory;
use App\Support\Localization\JalaliDate;
use App\Support\Localization\PersianNumber;
use App\Support\Localization\PersianTextNormalizer;
use App\Support\Localization\PhoneNormalizer;
use App\Support\Money\Money;
use App\Support\Money\MoneyFormatter;
use Closure;

/**
 * The command palette's record search: products, categories, orders and customers, each group only
 * for staff who may see it, a handful of results per group, all inside the current tenant.
 */
final class GlobalSearch
{
    private const PER_GROUP = 5;

    /**
     * @param  Closure(string): bool  $can
     * @return list<array{group: string, label: string, items: list<array<string, mixed>>}>
     */
    public static function run(string $term, Closure $can, string $timezone): array
    {
        $term = trim(PersianNumber::toLatin($term));
        if (mb_strlen($term) < 1) {
            return [];
        }

        $groups = [];

        if ($can('catalog.view')) {
            $soldOut = ProductAvailability::query()->where('status', 'sold_out')->pluck('product_id')->flip();
            $products = Product::query()->with('variants.prices')->search($term)->orderByDesc('is_active')->orderBy('name')->limit(self::PER_GROUP)->get()
                ->map(fn (Product $p) => [
                    'id' => $p->id,
                    'title' => $p->name,
                    'subtitle' => $p->is_active ? ($soldOut->has($p->id) ? 'تمام شده' : 'فعال') : 'غیرفعال',
                    'href' => "/dashboard/menu/{$p->id}",
                    'sold_out' => $soldOut->has($p->id),
                    'active' => $p->is_active,
                ])->values()->all();
            if ($products) {
                $groups[] = ['group' => 'products', 'label' => 'محصولات', 'items' => $products];
            }

            $name = PersianTextNormalizer::forSearch($term);
            if ($name !== '') {
                $categories = Category::query()->where('name', 'like', '%'.addcslashes($name, '%_\\').'%')->orderBy('sort')->limit(3)->get()
                    ->map(fn (Category $c) => ['id' => $c->id, 'title' => $c->name, 'subtitle' => 'دسته‌بندی', 'href' => '/dashboard/menu/categories'])
                    ->values()->all();
                if ($categories) {
                    $groups[] = ['group' => 'categories', 'label' => 'دسته‌بندی‌ها', 'items' => $categories];
                }
            }
        }

        if ($can('orders.view')) {
            $orders = OrderFilters::apply(['q' => $term])->with('branch')
                ->latest('placed_at')->limit(self::PER_GROUP)->get()
                ->map(fn (Order $o) => [
                    'id' => $o->id,
                    'title' => '#'.PersianNumber::toPersian((string) $o->daily_number).($o->contact_name ? ' • '.$o->contact_name : ''),
                    'subtitle' => $o->status->label().' • '.MoneyFormatter::format(Money::rials($o->total)).' • '.JalaliDate::format($o->placed_at, 'd MMMM HH:mm', $timezone),
                    'href' => "/dashboard/orders/{$o->id}",
                ])->values()->all();
            if ($orders) {
                $groups[] = ['group' => 'orders', 'label' => 'سفارش‌ها', 'items' => $orders];
            }
        }

        if ($can('customers.view')) {
            $customers = CustomerDirectory::query(['q' => $term])->orderByDesc('last_order_at')->limit(self::PER_GROUP)->get()
                ->map(fn (Customer $c) => [
                    'id' => $c->id,
                    'title' => $c->name ?: 'بدون نام',
                    'subtitle' => PersianNumber::toPersian(PhoneNormalizer::toLocal($c->phone_e164)),
                    'href' => "/dashboard/customers/{$c->id}",
                ])->values()->all();
            if ($customers) {
                $groups[] = ['group' => 'customers', 'label' => 'مشتریان', 'items' => $customers];
            }
        }

        return $groups;
    }
}
