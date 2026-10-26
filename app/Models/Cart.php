<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['last_activity_at' => 'datetime'];
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(CartItem::class); }
}
