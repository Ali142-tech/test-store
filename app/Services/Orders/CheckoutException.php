<?php

namespace App\Services\Orders;

use RuntimeException;

class CheckoutException extends RuntimeException
{
    /** @param  array<int,string>  $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(implode(' ', $errors));
    }
}
