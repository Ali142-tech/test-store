<?php

namespace App\Support;

/**
 * Original, brand-neutral product illustrations (SVG). These are NOT product photographs: they are
 * placeholders that show the product type and key spec (e.g. "30W", "10,000 mAh") until real,
 * licensed photos are uploaded in the admin panel.
 */
class ProductIllustration
{
    public static function svg(string $type, string $label = '', string $brand = '', ?int $hue = null): string
    {
        $hue ??= abs(crc32($brand ?: $type)) % 360;
        $c1 = "hsl($hue 78% 58%)";
        $c2 = 'hsl('.(($hue + 35) % 360).' 80% 42%)';
        $soft = "hsl($hue 90% 96%)";
        $soft2 = "hsl($hue 60% 90%)";
        $label = htmlspecialchars($label, ENT_XML1);
        $brand = htmlspecialchars(mb_strtoupper($brand), ENT_XML1);

        $shape = match ($type) {
            'powerbank' => self::powerbank(),
            'charger' => self::charger(),
            'cable' => self::cable(),
            'earbuds' => self::earbuds(),
            'wireless' => self::wireless(),
            'car' => self::car(),
            default => self::charger(),
        };

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 800" role="img" aria-label="Illustration of {$type}">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{$soft}"/><stop offset="1" stop-color="{$soft2}"/></linearGradient>
    <linearGradient id="body" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{$c1}"/><stop offset="1" stop-color="{$c2}"/></linearGradient>
    <linearGradient id="white" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#e7e9ef"/></linearGradient>
    <linearGradient id="dark" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2a2d36"/><stop offset="1" stop-color="#0f1115"/></linearGradient>
    <filter id="sh" x="-20%" y="-20%" width="140%" height="150%"><feDropShadow dx="0" dy="22" stdDeviation="22" flood-color="#0f1115" flood-opacity=".22"/></filter>
  </defs>
  <rect width="800" height="800" fill="url(#bg)"/>
  <circle cx="650" cy="150" r="190" fill="#fff" opacity=".35"/><circle cx="130" cy="690" r="150" fill="#fff" opacity=".25"/>
  <ellipse cx="400" cy="650" rx="210" ry="26" fill="#0f1115" opacity=".12"/>
  <g filter="url(#sh)">{$shape}</g>
  <text x="400" y="735" text-anchor="middle" font-family="Inter,Segoe UI,Arial,sans-serif" font-size="46" font-weight="800" fill="#0f1115">{$label}</text>
  <text x="400" y="85" text-anchor="middle" font-family="Inter,Segoe UI,Arial,sans-serif" font-size="26" font-weight="700" letter-spacing="6" fill="#0f1115" opacity=".5">{$brand}</text>
</svg>
SVG;
    }

    private static function powerbank(): string
    {
        return <<<'S'
<rect x="270" y="150" width="260" height="490" rx="46" fill="url(#body)"/>
<rect x="282" y="162" width="236" height="466" rx="36" fill="none" stroke="#fff" stroke-opacity=".35" stroke-width="3"/>
<rect x="340" y="210" width="120" height="14" rx="7" fill="#fff" opacity=".85"/>
<g fill="#fff"><circle cx="330" cy="300" r="9" opacity=".95"/><circle cx="365" cy="300" r="9" opacity=".7"/><circle cx="400" cy="300" r="9" opacity=".45"/><circle cx="435" cy="300" r="9" opacity=".25"/></g>
<rect x="355" y="560" width="90" height="26" rx="13" fill="url(#dark)"/>
<path d="M360 400h80l-30 60h26l-60 80 14-58h-30z" fill="#fff" opacity=".9"/>
S;
    }

    private static function charger(): string
    {
        return <<<'S'
<rect x="300" y="330" width="22" height="120" rx="6" fill="#aab0bd" transform="translate(0 220)"/>
<rect x="478" y="330" width="22" height="120" rx="6" fill="#aab0bd" transform="translate(0 220)"/>
<rect x="260" y="170" width="280" height="400" rx="52" fill="url(#white)"/>
<rect x="260" y="170" width="280" height="400" rx="52" fill="none" stroke="#fff" stroke-width="4"/>
<rect x="335" y="205" width="130" height="46" rx="23" fill="url(#dark)"/>
<rect x="352" y="219" width="96" height="18" rx="9" fill="#3b3f4b"/>
<rect x="300" y="290" width="200" height="220" rx="34" fill="url(#body)" opacity=".92"/>
<path d="M410 320l-52 86h40l-12 70 56-92h-40z" fill="#fff"/>
S;
    }

    private static function cable(): string
    {
        return <<<'S'
<path d="M200 560 C 200 330, 360 640, 400 420 S 600 250, 600 300" fill="none" stroke="#0f1115" stroke-opacity=".18" stroke-width="30" stroke-linecap="round" transform="translate(0 14)"/>
<path d="M200 560 C 200 330, 360 640, 400 420 S 600 250, 600 300" fill="none" stroke="url(#body)" stroke-width="26" stroke-linecap="round"/>
<g><rect x="165" y="545" width="70" height="86" rx="16" fill="url(#white)"/><rect x="180" y="620" width="40" height="26" rx="6" fill="url(#dark)"/></g>
<g transform="rotate(-8 600 300)"><rect x="560" y="196" width="80" height="112" rx="18" fill="url(#white)"/><rect x="575" y="170" width="50" height="34" rx="10" fill="url(#dark)"/></g>
S;
    }

    private static function earbuds(): string
    {
        return <<<'S'
<rect x="230" y="330" width="340" height="270" rx="80" fill="url(#white)"/>
<path d="M230 440h340" stroke="#c9ceda" stroke-width="4"/>
<circle cx="400" cy="440" r="14" fill="url(#body)"/>
<g transform="rotate(-18 330 270)"><ellipse cx="330" cy="240" rx="48" ry="56" fill="url(#body)"/><rect x="316" y="270" width="28" height="110" rx="14" fill="url(#body)"/><circle cx="330" cy="236" r="16" fill="#fff" opacity=".6"/></g>
<g transform="rotate(18 470 270)"><ellipse cx="470" cy="240" rx="48" ry="56" fill="url(#body)"/><rect x="456" y="270" width="28" height="110" rx="14" fill="url(#body)"/><circle cx="470" cy="236" r="16" fill="#fff" opacity=".6"/></g>
S;
    }

    private static function wireless(): string
    {
        return <<<'S'
<ellipse cx="400" cy="520" rx="230" ry="70" fill="url(#dark)"/>
<ellipse cx="400" cy="500" rx="230" ry="70" fill="url(#white)"/>
<ellipse cx="400" cy="500" rx="160" ry="44" fill="none" stroke="url(#body)" stroke-width="10"/>
<ellipse cx="400" cy="500" rx="90" ry="24" fill="url(#body)"/>
<g fill="none" stroke="url(#body)" stroke-width="10" stroke-linecap="round" opacity=".8"><path d="M330 400a90 40 0 0 1 140 0"/><path d="M300 350a130 60 0 0 1 200 0" opacity=".6"/><path d="M270 300a170 80 0 0 1 260 0" opacity=".35"/></g>
S;
    }

    private static function car(): string
    {
        return <<<'S'
<rect x="340" y="140" width="120" height="100" rx="20" fill="url(#white)"/>
<rect x="320" y="230" width="160" height="330" rx="44" fill="url(#dark)"/>
<rect x="320" y="230" width="160" height="330" rx="44" fill="none" stroke="#fff" stroke-opacity=".2" stroke-width="3"/>
<circle cx="400" cy="330" r="46" fill="none" stroke="url(#body)" stroke-width="12"/>
<circle cx="400" cy="330" r="14" fill="url(#body)"/>
<rect x="360" y="440" width="80" height="24" rx="12" fill="#3b3f4b"/><rect x="360" y="480" width="80" height="24" rx="12" fill="#3b3f4b"/>
S;
    }
}
