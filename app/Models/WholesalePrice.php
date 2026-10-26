<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WholesalePrice extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'expires_at' => 'datetime'];
    }
}
