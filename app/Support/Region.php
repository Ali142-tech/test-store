<?php

namespace App\Support;

use App\Models\StoreSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Which currency this visitor sees, decided per request from their country.
 *
 *   USA / Canada     -> USD      United Kingdom -> GBP      Eurozone -> EUR      everyone else (incl. Pakistan) -> PKR
 *
 * Prices are always stored and charged in PKR. For USD/GBP/EUR visitors every unit price carries an extra fee
 * (default US$30, converted to PKR at the store's own rate) that is added server-side in PriceResolver, then the PKR
 * amount is shown in the visitor's currency at the fixed rates set in Admin > Store settings.
 */
class Region
{
    public const SYMBOLS = ['PKR' => 'Rs', 'USD' => '$', 'GBP' => '£', 'EUR' => '€'];

    /** PKR per 1 unit; only used until the owner enters real rates in Admin > Store settings. */
    public const DEFAULT_RATES = ['USD' => 280.0, 'GBP' => 370.0, 'EUR' => 325.0];

    public const DEFAULT_EXTRA_USD = 30.0;

    private const USD = ['US', 'CA'];

    private const GBP = ['GB'];

    private const EUR = ['AT', 'BE', 'BG', 'HR', 'CY', 'EE', 'FI', 'FR', 'DE', 'GR', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PT', 'SK', 'SI', 'ES'];

    /** Currency code for the current request (PKR when unknown, e.g. in the console). */
    public static function currency(): string
    {
        $request = request();
        if (! $request) {
            return 'PKR';
        }

        return $request->attributes->get('region_currency') ?? tap(self::detect($request), fn ($c) => $request->attributes->set('region_currency', $c));
    }

    public static function isInternational(): bool
    {
        return self::currency() !== 'PKR';
    }

    public static function symbol(?string $currency = null): string
    {
        return self::SYMBOLS[$currency ?? self::currency()] ?? 'Rs';
    }

    /** PKR per 1 unit of the currency (1 for PKR). */
    public static function rate(?string $currency = null): float
    {
        $currency ??= self::currency();
        if ($currency === 'PKR') {
            return 1.0;
        }

        // A blank / zero / non-numeric setting must never mean "1 PKR = 1 dollar": fall back to the default rate.
        $rate = (float) StoreSetting::get('rate_'.strtolower($currency));

        return $rate > 0 ? $rate : self::DEFAULT_RATES[$currency];
    }

    /** The per-unit international fee expressed in PKR (0 for Pakistan/other regions). */
    public static function extraPkr(): float
    {
        if (! self::isInternational()) {
            return 0.0;
        }

        $extra = StoreSetting::get('extra_usd');
        $extra = is_numeric($extra) ? (float) $extra : self::DEFAULT_EXTRA_USD; // blank = default; 0 = fee switched off

        return round(max(0.0, $extra) * self::rate('USD'), 2);
    }

    /** Amount in PKR to the visitor's currency, as a number. */
    public static function convert(float $pkr, ?string $currency = null): float
    {
        $currency ??= self::currency();

        return $currency === 'PKR' ? $pkr : $pkr / self::rate($currency);
    }

    public static function country(\Illuminate\Http\Request $request): ?string
    {
        // Cloudflare / other CDNs / load balancers pass the country; this is free and instant.
        foreach (['CF-IPCountry', 'CloudFront-Viewer-Country', 'X-Country-Code'] as $h) {
            $v = strtoupper(trim((string) $request->header($h)));
            if (preg_match('/^[A-Z]{2}$/', $v) && $v !== 'XX' && $v !== 'T1') {
                return $v;
            }
        }

        $ip = $request->ip();
        if (! $ip || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null; // localhost / private networks
        }

        // No CDN header: look the IP up once a day and remember the answer (failures are remembered for 10 minutes).
        return Cache::remember('geo:'.$ip, now()->addDay(), function () use ($ip) {
            try {
                $code = Http::timeout(2)->get('http://ip-api.com/json/'.$ip, ['fields' => 'status,countryCode'])->json('countryCode');
            } catch (\Throwable) {
                $code = null;
            }

            return is_string($code) && preg_match('/^[A-Z]{2}$/', $code) ? $code : '';
        }) ?: null;
    }

    private static function detect(\Illuminate\Http\Request $request): string
    {
        // Testing aid only: ?currency=USD (never honoured in production, so nobody can dodge the fee).
        if (app()->environment('local', 'testing') && $request->hasSession()) {
            $asked = strtoupper((string) $request->query('currency'));
            if (array_key_exists($asked, self::SYMBOLS)) {
                $request->session()->put('forced_currency', $asked);
            }
            if ($forced = $request->session()->get('forced_currency')) {
                return $forced;
            }
        }

        $country = self::country($request);

        return match (true) {
            in_array($country, self::USD, true) => 'USD',
            in_array($country, self::GBP, true) => 'GBP',
            in_array($country, self::EUR, true) => 'EUR',
            default => 'PKR',
        };
    }
}
