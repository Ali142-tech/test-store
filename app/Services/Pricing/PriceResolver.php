<?php

namespace App\Services\Pricing;

use App\Models\ProductVariant;
use App\Models\User;
use App\Support\Region;

/**
 * Single server-side authority for unit prices.
 * Order: negotiated (approved wholesale) > quantity tier (approved wholesale) > sale > regular.
 * Wholesale pricing is only ever returned for approved wholesale users on wholesale-enabled products.
 */
class PriceResolver
{
    /** @return array{price: string, source: string} */
    public function resolve(ProductVariant $variant, int $quantity, ?User $user = null): array
    {
        $variant->loadMissing('product', 'tiers');

        if ($user && $user->isApprovedWholesale() && $variant->product->wholesale_enabled) {
            $negotiated = $user->negotiatedPrices()
                ->where('product_variant_id', $variant->id)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->first();

            if ($negotiated) {
                return ['price' => (string) $negotiated->price, 'source' => 'negotiated'];
            }

            $tier = $variant->tiers
                ->filter(fn ($t) => $quantity >= $t->min_quantity && ($t->max_quantity === null || $quantity <= $t->max_quantity))
                ->sortByDesc('min_quantity')
                ->first();

            if ($tier && bccomp((string) $tier->price, $variant->currentRetailPrice(), 2) <= 0) {
                return ['price' => $this->withFee((string) $tier->price), 'source' => 'tier'];
            }
        }

        return [
            'price' => $this->withFee($variant->currentRetailPrice()),
            'source' => $variant->onSale() ? 'sale' : 'regular',
        ];
    }

    /** International visitors (USD/GBP/EUR regions) pay a per-unit fee on top of the catalogue price; nobody else does. */
    public function withFee(string $price): string
    {
        return bcadd($price, number_format(Region::extraPkr(), 2, '.', ''), 2);
    }

    /** Minimum quantity this user must buy of the variant (MOQ only binds approved wholesale buyers). */
    public function minimumQuantity(ProductVariant $variant, ?User $user = null): int
    {
        $variant->loadMissing('product');

        if ($user && $user->isApprovedWholesale() && $variant->product->wholesale_enabled) {
            return max(1, (int) $variant->wholesale_moq);
        }

        return 1;
    }

    /** Tier table to display; empty for anyone who is not an approved wholesale buyer. */
    public function visibleTiers(ProductVariant $variant, ?User $user = null)
    {
        $variant->loadMissing('product', 'tiers');

        if ($user && $user->isApprovedWholesale() && $variant->product->wholesale_enabled) {
            return $variant->tiers;
        }

        return collect();
    }

    public function lineTotal(string $unitPrice, int $quantity): string
    {
        return bcmul($unitPrice, (string) $quantity, 2);
    }
}
