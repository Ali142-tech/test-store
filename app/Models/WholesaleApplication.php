<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WholesaleApplication extends Model
{
    // status/review fields can only be set by explicit admin code, never mass-assigned.
    protected $guarded = ['id', 'status', 'reviewed_by', 'reviewed_at', 'admin_notes'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
