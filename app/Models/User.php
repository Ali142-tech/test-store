<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    // customer_type and is_active are deliberately NOT mass assignable.
    protected $fillable = ['name', 'email', 'phone', 'password', 'marketing_consent'];

    protected $attributes = ['customer_type' => 'retail', 'is_active' => true, 'marketing_consent' => false];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'marketing_consent' => 'boolean',
        ];
    }

    public function wholesaleApplication(): HasOne
    {
        return $this->hasOne(WholesaleApplication::class);
    }

    public function negotiatedPrices(): HasMany
    {
        return $this->hasMany(WholesalePrice::class);
    }

    /** Server-side source of truth for wholesale access: type flag AND an approved application. */
    public function isApprovedWholesale(): bool
    {
        return $this->customer_type === 'wholesale'
            && $this->wholesaleApplication?->status === 'approved';
    }

    /** Only active staff (users holding a role) may enter the admin panel, in every environment. */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->isStaff();
    }

    public function isStaff(): bool
    {
        return $this->roles()->exists();
    }
}
