<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    protected $guarded = ['id'];

    public function url(): string
    {
        return str_starts_with($this->path, 'http') ? $this->path : Storage::disk('public')->url($this->path);
    }
}
