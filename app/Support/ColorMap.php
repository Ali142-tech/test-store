<?php

namespace App\Support;

/** Maps product colour names ("Midnight Black", "Sky Blue"...) to a CSS colour for swatches. Unknown names => null (text only). */
class ColorMap
{
    private const KEYWORDS = [
        'black' => '#111318', 'white' => '#f4f4f6', 'grey' => '#8b8f99', 'gray' => '#8b8f99', 'silver' => '#c4c8d0', 'gold' => '#d4af37',
        'rose' => '#e8a5b4', 'pink' => '#f4a6c0', 'red' => '#d62839', 'orange' => '#f08a24', 'yellow' => '#f5d142', 'green' => '#2f9e5b',
        'blue' => '#2f6fe0', 'navy' => '#1c2b5a', 'purple' => '#7b4fd6', 'violet' => '#7b4fd6', 'brown' => '#7a5230', 'beige' => '#d9c7a3',
        'cream' => '#efe5cf', 'titanium' => '#9a9a94', 'lavender' => '#b9a8e6', 'mint' => '#a8e0c8', 'cyan' => '#35c4d6', 'teal' => '#1f9aa6',
        'bronze' => '#a77a45', 'coffee' => '#6b4a35', 'khaki' => '#b9a972',
    ];

    public static function css(?string $name): ?string
    {
        $n = strtolower((string) $name);
        foreach (self::KEYWORDS as $word => $css) {
            if (str_contains($n, $word)) {
                return $css;
            }
        }

        return null;
    }
}
