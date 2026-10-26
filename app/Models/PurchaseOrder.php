<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['ordered_at' => 'datetime', 'received_at' => 'datetime'];
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(PurchaseOrderItem::class); }
    public function supplier(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Supplier::class); }
}
