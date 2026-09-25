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

        App::setLocale(in_array($locale, ['az', 'en', 'ru'], true)
            ? $locale
            : config('app.locale'));

        return $next($request);
    }
}
