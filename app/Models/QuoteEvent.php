<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuoteEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }
}
