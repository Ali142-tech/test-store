<?php

namespace App\Services\Shipping;

use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use Illuminate\Support\Collection;

class ShippingService
{
    /**
     * Shipping options for a destination, computed entirely on the server.
     *
     * @return Collection<int, array{method: ShippingMethod, cost: string, eta: ?string}>
     */
    public function options(?string $city, string $payableSubtotal, float $weightKg, string $audience, bool $freeShippingCoupon = false): Collection
    {
        $city = mb_strtolower(trim((string) $city));

        $zones = ShippingZone::where('is_active', true)->with(['methods' => fn ($q) => $q->where('is_active', true)])->get()
            ->filter(fn ($z) => empty($z->cities) || in_array($city, array_map('mb_strtolower', $z->cities), true));

        return $zones->flatMap->methods
            ->filter(fn ($m) => in_array($m->audience, ['all', $audience], true))
            ->map(function (ShippingMethod $m) use ($payableSubtotal, $weightKg, $freeShippingCoupon) {
                $cost = bcadd((string) $m->base_rate, bcmul((string) $m->per_kg_rate, (string) ceil($weightKg), 2), 2);
                if ($freeShippingCoupon || ($m->free_over !== null && bccomp($payableSubtotal, (string) $m->free_over, 2) >= 0)) {
                    $cost = '0.00';
                }
                $eta = $m->min_days ? ($m->max_days && $m->max_days !== $m->min_days ? "{$m->min_days}-{$m->max_days} days" : "{$m->min_days} days") : null;

                return ['method' => $m, 'cost' => $cost, 'eta' => $eta];
            })->values();
    }

    public function find(int $methodId, ?string $city, string $payableSubtotal, float $weightKg, string $audience, bool $freeShippingCoupon = false): ?array
    {
        return $this->options($city, $payableSubtotal, $weightKg, $audience, $freeShippingCoupon)->first(fn ($o) => $o['method']->id === $methodId);
    }
}
