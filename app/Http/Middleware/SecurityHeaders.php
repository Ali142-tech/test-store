<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    /** Headers that never vary by page. Personalised routes also get no-store so shared caches cannot leak them. */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if ($request->user() || $request->routeIs('cart.*', 'checkout.*', 'order.*', 'account.*', 'wholesale.*', 'quotes.*')) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }
}
