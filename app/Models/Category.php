<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saved(fn () => \Illuminate\Support\Facades\Cache::forget('nav.categories'));
        static::deleted(fn () => \Illuminate\Support\Facades\Cache::forget('nav.categories'));
    }

    /** Categories that have at least one active product (empty categories are hidden from the storefront). */
    public function scopeVisible(\Illuminate\Database\Eloquent\Builder $q): \Illuminate\Database\Eloquent\Builder
    {
        return $q->where('is_active', true)->whereHas('products', fn ($p) => $p->where('status', 'active'));
    }

    public function coverUrl(): ?string
    {
        return $this->products()->where('status', 'active')->whereHas('images')->with('images')->first()?->primaryImageUrl();
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
