<?php

namespace App\Http\Middleware;

use App\Support\ShortUrl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Qısa domen (SHORT_URL, məs. paf.az) yalnız yönləndiricidir: paf.az/p/AbC → {APP_URL}/p/AbC.
 * Səhifələr qısa domendə açılmır — sessiya, giriş, referal cookie-si və bankın geri qaytarması əsas saytdadır.
 */
class RedirectShortDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $shortHost = ShortUrl::host();

        if (!$shortHost) {
            return $next($request);
        }

        $host = strtolower($request->getHost());
        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        if ($host === $appHost || !in_array($host, [strtolower($shortHost), 'www.'.strtolower($shortHost)], true)) {
            return $next($request);
        }

        abort_unless($request->isMethod('GET') || $request->isMethod('HEAD'), 404);

        return redirect()->away(rtrim((string) config('app.url'), '/').$request->getRequestUri(), 302);
    }
}
