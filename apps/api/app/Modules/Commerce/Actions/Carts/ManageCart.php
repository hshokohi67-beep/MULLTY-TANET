<?php

namespace App\Modules\Commerce\Actions\Carts;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Commerce\Enums\OrderType;
use App\Modules\Commerce\Exceptions\CommerceException;
use App\Modules\Commerce\Models\Cart;
use App\Modules\Commerce\Models\CartItem;
use App\Modules\Commerce\Models\OrderSession;
use App\Modules\Core\Models\Branch;
use App\Support\Security\SecretToken;
use Illuminate\Support\Facades\DB;

/**
 * Carts hold choices only (product, variant, modifiers, qty, note); prices come from the
 * pricer. Deep validation (modifier rules, availability) happens when pricing, so a
 * customer can see *why* a line can't be ordered instead of it silently disappearing.
 */
final class ManageCart
{
    /** @return array{cart: Cart, token: string} */
    public function create(Branch $branch, OrderType $type, ?OrderSession $session = null, ?string $customerId = null): array
    {
        $token = SecretToken::generate();

        $cart = Cart::query()->create([
            'branch_id' => $branch->getKey(),
            'customer_id' => $customerId,
            'order_session_id' => $session?->getKey(),
            'cart_token_hash' => SecretToken::hash($token),
            'order_type' => $type,
            'status' => 'active',
            'expires_at' => now()->addDays(Cart::TTL_DAYS),
        ]);

        return ['cart' => $cart, 'token' => $token];
    }

    /**
     * @param  bool  $allowConverted  checkout replays read the cart the first attempt converted
     */
    public function find(?string $token, bool $allowConverted = false): Cart
    {
        if (! SecretToken::looksValid($token)) {
            throw CommerceException::cartInvalid();
        }

        $cart = Cart::query()->with(['items', 'branch', 'session'])->where('cart_token_hash', SecretToken::hash((string) $token))->first();

        $usable = $cart !== null && ($cart->isUsable() || ($allowConverted && $cart->status === 'converted'));

        if (! $usable || ($cart->session !== null && $cart->status === 'active' && ! $cart->session->isUsable())) {
            throw CommerceException::cartInvalid();
        }

        return $cart;
    }

    /**
     * Adds a line; an identical line (same variant, modifiers and note) is merged by quantity.
     *
     * @param  list<string>  $modifierIds
     */
    public function add(Cart $cart, string $variantId, int $quantity, array $modifierIds, ?string $note): CartItem
    {
        return DB::transaction(function () use ($cart, $variantId, $quantity, $modifierIds, $note): CartItem {
            $variant = ProductVariant::query()->with('product')->findOrFail($variantId);
            $modifierIds = array_values(array_unique($modifierIds));
            sort($modifierIds);

            $same = CartItem::query()->where('cart_id', $cart->getKey())->where('variant_id', $variantId)->lockForUpdate()->get()
                ->first(fn (CartItem $i) => ($i->modifier_ids ?? []) === $modifierIds && $i->note === $note);

            if ($same !== null) {
                $same->update(['quantity' => min(CartItem::MAX_QUANTITY, $same->quantity + $quantity)]);

                return $same;
            }

            if (CartItem::query()->where('cart_id', $cart->getKey())->count() >= Cart::MAX_LINES) {
                throw CommerceException::cartFull();
            }

            $this->touch($cart);

            return CartItem::query()->create([
                'cart_id' => $cart->getKey(),
                'product_id' => $variant->product_id,
                'variant_id' => $variant->getKey(),
                'quantity' => $quantity,
                'modifier_ids' => $modifierIds,
                'note' => $note,
            ]);
        });
    }

    public function update(Cart $cart, string $itemId, int $quantity, ?string $note): void
    {
        $item = CartItem::query()->where('cart_id', $cart->getKey())->findOrFail($itemId);

        $quantity === 0 ? $item->delete() : $item->update(['quantity' => $quantity, 'note' => $note ?? $item->note]);
        $this->touch($cart);
    }

    public function remove(Cart $cart, string $itemId): void
    {
        CartItem::query()->where('cart_id', $cart->getKey())->findOrFail($itemId)->delete();
        $this->touch($cart);
    }

    /**
     * @return list<array{product_id: string, variant_id: string, quantity: int, modifier_ids: list<string>, note: ?string, ref: string}>
     */
    public function lines(Cart $cart): array
    {
        return $cart->items->map(fn (CartItem $i) => [
            'product_id' => $i->product_id,
            'variant_id' => $i->variant_id,
            'quantity' => $i->quantity,
            'modifier_ids' => $i->modifier_ids ?? [],
            'note' => $i->note,
            'ref' => $i->id,
        ])->values()->all();
    }

    private function touch(Cart $cart): void
    {
        $cart->update(['expires_at' => now()->addDays(Cart::TTL_DAYS)]);
        $cart->session?->update(['last_activity_at' => now()]);
    }
}
