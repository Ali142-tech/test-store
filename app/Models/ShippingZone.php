<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingZone extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['cities' => 'array', 'is_active' => 'boolean'];
    }

    public function methods(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(ShippingMethod::class); }
}
