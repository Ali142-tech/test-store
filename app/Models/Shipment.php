<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['shipped_at' => 'datetime', 'delivered_at' => 'datetime'];
    }

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Order::class); }
    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(ShipmentItem::class); }
}
