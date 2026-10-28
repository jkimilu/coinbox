<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When FORCE_APP_URL=true, browser visits on another host (e.g. localhost:8000)
 * are redirected to the same path on APP_URL (e.g. the ngrok domain).
 */
class RedirectToAppUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        $appHost = parse_url(config('app.url'), PHP_URL_HOST);

        // API and Safaricom callbacks are never redirected.
        $isPage = $request->isMethod('GET') && ! $request->is('api/*', 'up');

        if (config('app.force_url') && $appHost && $isPage && $request->getHost() !== $appHost) {
            return redirect()->to(rtrim(config('app.url'), '/').$request->getRequestUri());
        }

        return $next($request);
    }
}
