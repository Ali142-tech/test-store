<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    // Cost price never leaves the server unless authorised code reads it explicitly.
    protected $hidden = ['cost_price'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'attributes' => 'array',
            'sale_starts_at' => 'datetime',
            'sale_ends_at' => 'datetime',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function tiers(): HasMany
    {
        return $this->hasMany(PriceTier::class)->orderBy('min_quantity');
    }

    public function available(): int
    {
        $this->loadMissing('product');

        // Bundles have no stock of their own: availability is the most complete sets their components allow.
        if ($this->product->type === 'bundle') {
            $items = BundleItem::where('bundle_product_id', $this->product_id)->with('variant')->get();

            return $items->isEmpty() ? 0 : (int) $items->map(fn ($i) => intdiv($i->variant->available(), max(1, $i->quantity)))->min();
        }

        return max(0, $this->stock_quantity - $this->reserved_quantity);
    }

    public function onSale(): bool
    {
        if ($this->sale_price === null || (float) $this->sale_price >= (float) $this->price) {
            return false;
        }
        $now = now();

        return (! $this->sale_starts_at || $this->sale_starts_at->lte($now))
            && (! $this->sale_ends_at || $this->sale_ends_at->gte($now));
    }

    public function currentRetailPrice(): string
    {
        return $this->onSale() ? (string) $this->sale_price : (string) $this->price;
    }

    /** This colour's own photo, else the product's main photo. */
    public function imageUrl(): ?string
    {
        $images = $this->product->images;

        return ($images->firstWhere('product_variant_id', $this->id) ?? $images->first())?->url();
    }

    public function displayName(): string
    {
        return trim($this->product->name.($this->name ? ' - '.$this->name : ''));
    }
}
