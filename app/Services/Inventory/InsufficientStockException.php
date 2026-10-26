<?php

namespace App\Services\Inventory;

use App\Models\ProductVariant;
use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(public readonly ProductVariant $variant, public readonly int $requested)
    {
        parent::__construct("Insufficient stock for {$variant->sku}: requested {$requested}, available {$variant->available()}.");
    }
}
