<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnRequest extends Model
{
    protected $guarded = ['id'];

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Order::class); }
    public function item(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(OrderItem::class, 'order_item_id'); }
}
