<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $guarded = ['id'];

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Order::class); }
    public function variant(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
}
