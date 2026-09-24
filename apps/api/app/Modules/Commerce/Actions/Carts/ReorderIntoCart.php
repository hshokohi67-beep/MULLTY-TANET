<?php

namespace App\Modules\Commerce\Actions\Carts;

use App\Modules\Catalog\Models\Modifier;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Commerce\Exceptions\CommerceException;
use App\Modules\Commerce\Models\Cart;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Commerce\Models\OrderItemModifier;

/**
 * "Order again": copies a past order's lines (variant, modifiers, quantity, note) into a cart.
 * Lines whose product or variant is gone or switched off are skipped and reported by name;
 * everything else is re-priced live by the cart quote, so old prices never come back.
 */
final class ReorderIntoCart
{
    public function __construct(private readonly ManageCart $carts) {}

    /** @return list<string> names of the skipped lines */
    public function handle(Order $order, Cart $cart): array
    {
        $order->loadMissing('items.modifiers');
        $variants = ProductVariant::query()->with('product')
            ->whereIn('id', $order->items->pluck('variant_id')->filter()->all())->get()->keyBy('id');
        $liveModifiers = Modifier::query()->where('is_active', true)
            ->whereIn('id', $order->items->flatMap(fn (OrderItem $i) => $i->modifiers->pluck('modifier_id'))->filter()->all())
            ->pluck('id')->flip();

        $skipped = [];
        $added = 0;

        foreach ($order->items as $item) {
            /** @var ProductVariant|null $variant */
            $variant = $item->variant_id ? $variants->get($item->variant_id) : null;

            if ($variant === null || ! $variant->is_active || ! $variant->product->is_active) {
                $skipped[] = $item->product_name;

                continue;
            }

            $modifierIds = $item->modifiers->map(fn (OrderItemModifier $m) => $m->modifier_id)
                ->filter(fn (?string $id) => $id !== null && $liveModifiers->has($id))->values()->all();

            try {
                $this->carts->add($cart, $variant->id, $item->quantity, $modifierIds, $item->note);
                $added++;
            } catch (CommerceException $e) {
                if ($added === 0 && $e->errorCode === 'cart_full') {
                    throw $e;
                }
                $skipped[] = $item->product_name;
            }
        }

        return $skipped;
    }
}
