<?php

namespace App\Support;

use App\Models\StoreSetting;

class Money
{
    public static function format(string|float|int|null $amount): string
    {
        $symbol = StoreSetting::get('currency_symbol', 'Rs');

        return $symbol.' '.number_format((float) $amount, 0);
    }
}
