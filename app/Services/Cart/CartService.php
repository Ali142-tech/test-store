<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Pricing\PriceResolver;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CartService
{
    public function __construct(private PriceResolver $prices) {}

    /** The current visitor's cart (created lazily). Guests are keyed by a random token kept in the session. */
    public function current(?User $user, bool $create = true): ?Cart
    {
        if ($user) {
            $cart = Cart::where('user_id', $user->id)->first();

            return $cart ?? ($create ? Cart::create(['user_id' => $user->id, 'last_activity_at' => now()]) : null);
        }

        $token = session('cart_token');
        if (! $token) {
            if (! $create) {
                return null;
            }
            $token = Str::random(40);
            session(['cart_token' => $token]);
        }

        $cart = Cart::where('session_id', $token)->whereNull('user_id')->first();

        return $cart ?? ($create ? Cart::create(['session_id' => $token, 'last_activity_at' => now()]) : null);
    }

    public function count(?User $user): int
    {
        $cart = $this->current($user, false);

        return $cart ? (int) $cart->items()->sum('quantity') : 0;
    }

    public function add(Cart $cart, int $variantId, int $quantity, ?User $user): CartItem
    {
        $variant = $this->purchasable($variantId);
        $existing = $cart->items()->where('product_variant_id', $variantId)->first();
        $newQty = ($existing?->quantity ?? 0) + $quantity;

        return $this->setQuantity($cart, $variant, $newQty, $user, $existing);
    }

    public function update(Cart $cart, int $variantId, int $quantity, ?User $user): ?CartItem
    {
        if ($quantity <= 0) {
            $this->remove($cart, $variantId);

            return null;
        }

        return $this->setQuantity($cart, $this->purchasable($variantId), $quantity, $user, $cart->items()->where('product_variant_id', $variantId)->first());
    }

    public function remove(Cart $cart, int $variantId): void
    {
        $cart->items()->where('product_variant_id', $variantId)->delete();
        $cart->touch();
    }

    /** Move a guest cart into the user's cart after login; quantities are re-validated, nothing is trusted. */
    public function mergeGuestCart(User $user, ?string $guestToken): void
    {
        if (! $guestToken) {
            return;
        }
        $guest = Cart::where('session_id', $guestToken)->whereNull('user_id')->first();
        if (! $guest) {
            return;
        }
        $mine = $this->current($user);
        foreach ($guest->items as $item) {
            try {
                $this->add($mine, $item->product_variant_id, $item->quantity, $user);
            } catch (InvalidArgumentException) {
                // Item no longer purchasable / insufficient stock: skip silently on merge.
            }
        }
        $guest->delete();
    }

    private function purchasable(int $variantId): ProductVariant
    {
        $variant = ProductVariant::with('product')->find($variantId);
        if (! $variant || ! $variant->is_active || $variant->product?->status !== 'active') {
            throw new InvalidArgumentException('This product is no longer available.');
        }

        return $variant;
    }

    private function setQuantity(Cart $cart, ProductVariant $variant, int $quantity, ?User $user, ?CartItem $existing): CartItem
    {
        $min = $this->prices->minimumQuantity($variant, $user);
        if ($quantity < $min) {
            throw new InvalidArgumentException("Minimum order quantity for this item is {$min}.");
        }
        if ($quantity > $variant->available()) {
            throw new InvalidArgumentException($variant->available() === 0 ? 'This item is out of stock.' : "Only {$variant->available()} left in stock.");
        }

        $item = $existing ?? new CartItem(['cart_id' => $cart->id, 'product_variant_id' => $variant->id]);
        $item->quantity = $quantity;
        $item->save();
        $cart->forceFill(['last_activity_at' => now()])->save();

        return $item;
    }
}
