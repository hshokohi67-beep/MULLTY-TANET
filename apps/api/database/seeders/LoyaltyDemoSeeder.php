<?php

namespace Database\Seeders;

use App\Modules\Catalog\Models\Category;
use App\Modules\Core\Models\TenantSetting;
use App\Modules\Customers\Models\Customer;
use App\Modules\Loyalty\Actions\PostPointsTransaction;
use App\Modules\Loyalty\Actions\PostWalletTransaction;
use App\Modules\Loyalty\Actions\UpdateTier;
use App\Modules\Loyalty\Enums\PointsTransactionType;
use App\Modules\Loyalty\Enums\WalletTransactionType;
use App\Modules\Loyalty\Models\CashbackRule;
use App\Modules\Loyalty\Models\LoyaltyAccount;
use App\Modules\Loyalty\Models\LoyaltyTier;
use App\Support\Money\CurrencyUnit;
use App\Support\Money\Money;
use Illuminate\Database\Seeder;

/**
 * A running customer club for «کافه نمونه» ("sample cafe"): three tiers, two cashback rules,
 * birthday and referral rewards, and a few members with history. Runs inside the tenant.
 */
class LoyaltyDemoSeeder extends Seeder
{
    public function run(PostWalletTransaction $wallet, PostPointsTransaction $points, UpdateTier $tiers): void
    {
        $t = fn (int $toman) => Money::fromUnit($toman, CurrencyUnit::Toman)->rials;

        foreach ([
            'loyalty.enabled' => '1',
            'loyalty.points_per_100k' => '1',
            'loyalty.point_value' => (string) $t(100),
            'loyalty.min_redeem_points' => '50',
            'loyalty.birthday_wallet_gift' => (string) $t(50_000),
            'loyalty.referral_referrer_reward' => (string) $t(30_000),
            'loyalty.referral_referee_reward' => (string) $t(20_000),
        ] as $key => $value) {
            TenantSetting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'is_encrypted' => false]);
        }

        LoyaltyTier::query()->create(['name' => 'برنزی', 'min_spend' => 0, 'color' => '#B45309', 'sort' => 1]);
        LoyaltyTier::query()->create(['name' => 'نقره‌ای', 'min_spend' => $t(2_000_000), 'color' => '#64748B', 'points_multiplier' => 15_000, 'perks' => '۱٫۵ برابر امتیاز', 'sort' => 2]);
        LoyaltyTier::query()->create(['name' => 'طلایی', 'min_spend' => $t(5_000_000), 'color' => '#CA8A04', 'points_multiplier' => 20_000, 'perks' => 'دو برابر امتیاز + قهوه‌ی رایگان تولد', 'sort' => 3]);

        CashbackRule::query()->create(['name' => '۵٪ کش‌بک خریدهای بالای ۵۰۰ هزار تومان', 'kind' => 'percent', 'value' => 500, 'min_spend' => $t(500_000), 'max_reward' => $t(100_000)]);
        $coffee = Category::query()->where('name', 'قهوه‌ی گرم')->value('id');
        if (is_string($coffee)) {
            CashbackRule::query()->create(['name' => 'قهوه‌دوست‌ها: ۲۰ هزار تومان برای ۲۰۰ هزار تومان قهوه', 'category_id' => $coffee, 'kind' => 'fixed', 'value' => $t(20_000), 'min_spend' => $t(200_000)]);
        }

        $members = [
            ['+989121000001', 'سارا محمدی', 7, 2, 3_200_000, 320, 85_000],
            ['+989121000002', 'رضا کریمی', 11, 20, 6_100_000, 910, 140_000],
            ['+989121000003', 'مریم احمدی', null, null, 450_000, 45, 0],
        ];

        foreach ($members as [$phone, $name, $month, $day, $spend, $earned, $cashback]) {
            $customer = Customer::query()->create(['phone_e164' => $phone, 'name' => $name, 'birth_month' => $month, 'birth_day' => $day]);
            $account = LoyaltyAccount::for($customer);
            $account->forceFill(['lifetime_spend' => $t($spend)])->save();
            $tiers->handle($account);

            $points->handle($customer, PointsTransactionType::Earn, $earned, "demo:{$phone}:points", 'امتیاز خریدهای گذشته');
            if ($cashback > 0) {
                $wallet->handle($customer, WalletTransactionType::Cashback, $t($cashback), "demo:{$phone}:cashback", 'کش‌بک خریدهای گذشته');
            }
        }
    }
}
