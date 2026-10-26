<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class StoreSetting extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('store_settings'));
        static::deleted(fn () => Cache::forget('store_settings'));
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::remember('store_settings', 3600, fn () => static::pluck('value', 'key')->all());

        return $all[$key] ?? $default;
    }
}
