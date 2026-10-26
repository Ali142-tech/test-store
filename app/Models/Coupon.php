<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['product_ids' => 'array', 'starts_at' => 'datetime', 'expires_at' => 'datetime', 'is_active' => 'boolean', 'value' => 'decimal:2'];
    }
}
