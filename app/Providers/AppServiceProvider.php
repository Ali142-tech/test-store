<?php

namespace App\Providers;

use App\Models\Coupon;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\StoreSetting;
use App\Models\User;
use App\Models\WholesaleApplication;
use App\Models\WholesalePrice;
use App\Observers\AuditObserver;
use App\Services\Payments\PaymentManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentManager::class);
    }

    public function boot(): void
    {
        if ($this->app->isProduction()) {
            URL::forceHttps();
        }

        // Super Admin bypasses permission checks; everyone else is checked against explicit permissions.
        Gate::before(fn ($user, $ability) => $user->hasRole('Super Admin') ? true : null);

        foreach ([Product::class, ProductVariant::class, PriceTier::class, WholesalePrice::class, Coupon::class, ShippingZone::class,
            ShippingMethod::class, WholesaleApplication::class, StoreSetting::class, Refund::class, User::class] as $model) {
            $model::observe(AuditObserver::class);
        }
    }
}
