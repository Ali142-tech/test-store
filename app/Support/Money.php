<?php

namespace App\Support;

use App\Models\Order;
use App\Models\StoreSetting;

/**
 * Money is stored and charged in PKR. format() is always PKR (admin screens). The storefront uses:
 *  - unit():  catalogue prices, with the international fee added for USD/GBP/EUR visitors
 *  - shop():  cart/checkout amounts that already contain the fee, only converted
 *  - order(): a placed order, in the currency and rate it was placed with
 */
class Money
{
    public static function format(string|float|int|null $amount): string
    {
        $symbol = StoreSetting::get('currency_symbol', 'Rs');

        return $symbol.' '.number_format((float) $amount, 0);
    }

    /** Catalogue price (PKR, no fee yet) in the visitor's currency. */
    public static function unit(string|float|int|null $pkr): string
    {
        return self::shop((float) $pkr + Region::extraPkr());
    }

    /** PKR amount converted for the visitor's currency. */
    public static function shop(string|float|int|null $pkr): string
    {
        $currency = Region::currency();

        return self::show((float) $pkr, $currency, Region::rate($currency));
    }

    /** Amount of a placed order, in the currency/rate snapshotted when it was placed (PKR for older orders). */
    public static function order(string|float|int|null $pkr, Order $order): string
    {
        $currency = $order->display_currency ?: 'PKR';

        return $currency === 'PKR' ? self::format($pkr) : self::show((float) $pkr, $currency, (float) $order->display_rate ?: 1.0);
    }

    /** Plain text variant for emails. */
    public static function show(float $pkr, string $currency, float $rate): string
    {
        if ($currency === 'PKR') {
            return self::format($pkr);
        }

        return Region::SYMBOLS[$currency].number_format($pkr / ($rate ?: 1), 2);
    }

    /** What the browser needs to format prices (product page / checkout scripts). */
    public static function js(): array
    {
        $currency = Region::currency();

        return ['symbol' => $currency === 'PKR' ? StoreSetting::get('currency_symbol', 'Rs') : Region::SYMBOLS[$currency], 'rate' => Region::rate($currency), 'decimals' => $currency === 'PKR' ? 0 : 2];
    }
}
