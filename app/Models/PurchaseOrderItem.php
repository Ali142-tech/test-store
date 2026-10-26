<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    protected $guarded = ['id'];

    public function variant(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
}
