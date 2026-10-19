<?php

namespace App\Modules\Loyalty\Http\Controllers;

use App\Modules\Catalog\Models\Category;
use App\Modules\Core\Support\TenantSettings;
use App\Modules\Loyalty\Models\CashbackRule;
use App\Modules\Loyalty\Models\LoyaltyTier;
use App\Support\Entitlements\EntitlementGate;
use Illuminate\Http\JsonResponse;

/**
 * The public «باشگاه مشتریان» page: only how the café's program works (tiers, rewards, cashback).
 * Explicit allow-list; never any customer data. 404 when the club is off or not in the plan.
 */
final class PublicClubController
{
    public function show(EntitlementGate $gate): JsonResponse
    {
        abort_unless($gate->enabled('loyalty') && TenantSettings::get('loyalty.enabled'), 404);

        $categories = Category::query()->pluck('name', 'id');

        return response()->json(['data' => [
            'points_per_100k' => (int) TenantSettings::get('loyalty.points_per_100k'),
            'point_value' => (int) TenantSettings::get('loyalty.point_value'),
            'min_redeem_points' => (int) TenantSettings::get('loyalty.min_redeem_points'),
            'birthday_wallet_gift' => (int) TenantSettings::get('loyalty.birthday_wallet_gift'),
            'birthday_points' => (int) TenantSettings::get('loyalty.birthday_points'),
            'referral_referrer_reward' => (int) TenantSettings::get('loyalty.referral_referrer_reward'),
            'referral_referee_reward' => (int) TenantSettings::get('loyalty.referral_referee_reward'),
            'wallet_payments' => (bool) TenantSettings::get('wallet.payments_enabled'),
            'tiers' => LoyaltyTier::query()->orderBy('min_spend')->get()->map(fn (LoyaltyTier $t) => [
                'name' => $t->name, 'color' => $t->color, 'min_spend' => $t->min_spend,
                'points_multiplier' => $t->points_multiplier, 'perks' => $t->perks,
            ])->values()->all(),
            'cashback' => CashbackRule::query()->where('is_active', true)->orderBy('min_spend')->get()->map(fn (CashbackRule $r) => [
                'name' => $r->name, 'kind' => $r->kind, 'value' => $r->value, 'min_spend' => $r->min_spend, 'max_reward' => $r->max_reward,
                'category' => $r->category_id !== null ? $categories->get($r->category_id) : null,
            ])->values()->all(),
        ]])->header('Cache-Control', 'public, max-age=60');
    }
}
