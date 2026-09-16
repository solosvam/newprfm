<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class setLangMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $subdomain = explode('.',$request->getHost())[0];
        $langugages = config()->get('lang');

        if(array_key_exists($subdomain,$langugages)){
            App::setLocale($subdomain);
        }else{
            App::setLocale(config('app.locale'));
        }

        return $next($request);
    }
}
