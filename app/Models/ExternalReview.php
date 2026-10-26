<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalReview extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['reviewed_on' => 'date'];
    }

    /** "Muzamil Jamali" -> "Muzamil J." (public display; avoids republishing full personal names). */
    public function displayName(): string
    {
        $parts = preg_split('/\s+/', trim($this->reviewer_name));

        return count($parts) > 1 ? $parts[0].' '.mb_substr(end($parts), 0, 1).'.' : ($parts[0] ?: 'Customer');
    }
}
