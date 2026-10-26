<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    protected $guarded = ['id'];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function url(): string
    {
        return self::urlFor($this->path);
    }

    /** Public URL for a media path. Local disks use a root-relative URL so it works on any host/port. */
    public static function urlFor(string $path): string
    {
        if (str_starts_with($path, 'http')) {
            return $path;
        }
        $disk = config('store.media_disk');

        return config("filesystems.disks.{$disk}.driver") === 'local'
            ? '/storage/'.ltrim($path, '/')
            : Storage::disk($disk)->url($path);
    }
}
