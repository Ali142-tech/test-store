<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quote extends Model
{
    protected $guarded = ['id'];

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(QuoteItem::class); }
    public function events(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(QuoteEvent::class); }
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class); }
}
