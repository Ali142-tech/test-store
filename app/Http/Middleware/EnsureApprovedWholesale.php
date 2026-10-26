<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Server-side gate for every wholesale-only endpoint. */
class EnsureApprovedWholesale
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->isApprovedWholesale(), 403, 'An approved wholesale account is required.');

        return $next($request);
    }
}
