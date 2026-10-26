<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'specifications' => 'array',
            'is_featured' => 'boolean',
            'is_best_seller' => 'boolean',
            'wholesale_enabled' => 'boolean',
            'is_authentic_verified' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Keep old URLs alive: record a 301 whenever a published slug changes.
        static::updating(function (self $p) {
            if ($p->isDirty('slug') && $p->getOriginal('slug')) {
                SlugRedirect::updateOrCreate(
                    ['old_path' => '/product/'.$p->getOriginal('slug')],
                    ['new_path' => '/product/'.$p->slug, 'status_code' => 301],
                );
            }
        });
    }

    public function bundleItems(): HasMany
    {
        return $this->hasMany(BundleItem::class, 'bundle_product_id');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->chaperone();
    }

    public function defaultVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class)->where('is_active', true)->orderByDesc('is_default')->orderBy('price')->chaperone();
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function deviceModels(): BelongsToMany
    {
        return $this->belongsToMany(DeviceModel::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class)->where('status', 'approved');
    }

    public function related(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'product_relations', 'product_id', 'related_product_id')
            ->wherePivot('kind', 'related');
    }

    public function externalReviews(): HasMany
    {
        return $this->hasMany(ExternalReview::class)->latest('reviewed_on');
    }

    /** Everything a product card needs, in one eager-loaded query set (incl. colour options). */
    public function scopeForCards(Builder $q): Builder
    {
        return $q->with(['defaultVariant', 'variants' => fn ($v) => $v->where('is_active', true), 'images', 'brand'])->withAvg('reviews', 'rating')->withCount('reviews');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }

    public function scopeInStock(Builder $q): Builder
    {
        return $q->whereHas('variants', fn ($v) => $v->where('is_active', true)->whereRaw('stock_quantity - reserved_quantity > 0'));
    }

    public function primaryImageUrl(): ?string
    {
        return $this->images->first()?->url();
    }

    /** Distinct colour names across active variants (for swatches). */
    public function colorOptions(): \Illuminate\Support\Collection
    {
        return $this->variants->pluck('color')->filter()->unique()->values();
    }
}
