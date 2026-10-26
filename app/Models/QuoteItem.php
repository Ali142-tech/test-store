<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuoteItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['target_price' => 'decimal:2', 'quoted_price' => 'decimal:2'];
    }

    public function variant(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
}
