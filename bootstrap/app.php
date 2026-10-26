<?php

use App\Http\Middleware\EnsureApprovedWholesale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias(['wholesale' => EnsureApprovedWholesale::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->appendToGroup('web', \App\Http\Middleware\SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Old product URLs (renamed slugs) redirect permanently to the new URL.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, \Illuminate\Http\Request $request) {
            $r = \App\Models\SlugRedirect::where('old_path', '/'.$request->path())->first();

            return $r ? redirect($r->new_path, $r->status_code) : null;
        });
    })->create();
