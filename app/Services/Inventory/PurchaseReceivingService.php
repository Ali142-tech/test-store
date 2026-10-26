<?php

namespace App\Services\Inventory;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PurchaseReceivingService
{
    public function __construct(private StockService $stock) {}

    /** Receive every outstanding unit on a purchase order; stock + ledger + cost price are updated atomically. */
    public function receiveAll(PurchaseOrder $po, User $by): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $by) {
            $po = PurchaseOrder::lockForUpdate()->with('items.variant')->findOrFail($po->id);
            if (in_array($po->status, ['received', 'cancelled'], true)) {
                throw new InvalidArgumentException("A {$po->status} purchase order cannot be received.");
            }

            $total = '0.00';
            foreach ($po->items as $item) {
                $outstanding = $item->quantity - $item->received_quantity;
                if ($outstanding > 0) {
                    $this->stock->add($item->product_variant_id, $outstanding, 'purchase', $po, $by->id, "PO {$po->reference}");
                    $item->update(['received_quantity' => $item->quantity]);
                    // Latest landed cost becomes the variant's cost price (inventory valuation uses it).
                    $item->variant->update(['cost_price' => $item->unit_cost]);
                }
                $total = bcadd($total, bcmul((string) $item->unit_cost, (string) $item->quantity, 2), 2);
            }

            $po->update(['status' => 'received', 'received_at' => now(), 'total_cost' => $total]);

            return $po;
        });
    }
}
