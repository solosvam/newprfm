<?php

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * "throttle:N,M" — hər route-un öz sayğacı olsun. Laravel-in standart açarı yalnız istifadəçi / IP-dir:
 * bütün throttle-lı route-lar bir sayğacı bölüşürdü (məs. admin köməkçi / kuryer sorğuları /admin/reset-in 5/dəq limitini doldururdu → 429).
 * İstifadəçi: admin guard-ı da nəzərə alınır (admin sorğusunda $request->user() boşdur — əvvəl IP-yə düşürdü).
 */
class ThrottlePerRoute extends ThrottleRequests
{
    protected function resolveRequestSignature($request)
    {
        $route = $request->route();
        $scope = $request->method().'|'.($route?->getName() ?? $route?->uri() ?? $request->path());

        if ($admin = $request->user('admin')) {
            return sha1('admin:'.$admin->getAuthIdentifier().'|'.$scope);
        }
        if ($user = $request->user()) {
            return sha1('web:'.$user->getAuthIdentifier().'|'.$scope);
        }

        return sha1(($route?->getDomain() ?? '').'|'.$request->ip().'|'.$scope);
    }
}
