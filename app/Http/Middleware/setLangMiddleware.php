<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class setLangMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->hasSession()
            ? $request->session()->get('locale', config('app.locale'))
            : config('app.locale');

        // ?lang=en|ru — dil versiyasının ünvanı (hreflang, sitemap); sessiyaya da yazılır ki sayt həmin dildə davam etsin
        $query = $request->query('lang');
        if (is_string($query) && in_array($query, ['az', 'en', 'ru'], true)) {
            $locale = $query;
            if ($request->hasSession()) {
                $request->session()->put('locale', $locale);
            }
        }

        App::setLocale(in_array($locale, ['az', 'en', 'ru'], true)
            ? $locale
            : config('app.locale'));

        return $next($request);
    }
}
