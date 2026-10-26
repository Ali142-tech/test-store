<?php

namespace App\Services\Inventory;

use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The only place stock quantities change. Every change locks the variant row
 * and writes a stock_movements record inside one transaction.
 */
class StockService
{
    public function remove(int $variantId, int $quantity, string $reason = 'sale', ?Model $reference = null, ?int $userId = null, ?string $note = null): ProductVariant
    {
        return $this->change($variantId, -abs($quantity), $reason, $reference, $userId, $note, enforceAvailability: true);
    }

    public function add(int $variantId, int $quantity, string $reason, ?Model $reference = null, ?int $userId = null, ?string $note = null): ProductVariant
    {
        return $this->change($variantId, abs($quantity), $reason, $reference, $userId, $note);
    }

    /** Manual admin adjustment (positive or negative). */
    public function adjust(int $variantId, int $delta, string $reason, ?int $userId = null, ?string $note = null): ProductVariant
    {
        return $this->change($variantId, $delta, $reason, null, $userId, $note, enforceAvailability: $delta < 0);
    }

    private function change(int $variantId, int $delta, string $reason, ?Model $reference, ?int $userId, ?string $note, bool $enforceAvailability = false): ProductVariant
    {
        return DB::transaction(function () use ($variantId, $delta, $reason, $reference, $userId, $note, $enforceAvailability) {
            $variant = ProductVariant::query()->lockForUpdate()->findOrFail($variantId);

            if ($enforceAvailability && $variant->stock_quantity + $delta < 0) {
                throw new InsufficientStockException($variant, abs($delta));
            }

            $variant->stock_quantity += $delta;
            $variant->save();

            StockMovement::create([
                'product_variant_id' => $variant->id,
                'quantity_change' => $delta,
                'quantity_after' => $variant->stock_quantity,
                'reason' => $reason,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'note' => $note,
                'user_id' => $userId,
            ]);

            return $variant;
        });
    }
}
