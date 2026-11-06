<?php

namespace App\Http\Middleware;

use App\Services\FinanceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin paneli: kuryer (CRM icazəsi olmayan) panelin səhifəsini açanda kuryer tətbiqinə (/kuryer) yönləndirilir.
 * Köhnə linklər (Telegram, əlfəcin) uyğun səhifəyə gedir. Çıxış və bildiriş sorğuları toxunulmur.
 */
class CouriersToCourierApp
{
    private const KEEP = ['admin.logout', 'admin.notifications.*'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('admin');
        if (!$user || !$request->isMethod('GET') || $request->routeIs(...self::KEEP)) {
            return $next($request);
        }
        // Rol yoxlaması alınmasa (məs. rol cədvəlləri olmayan mühit) — panel olduğu kimi açılır
        if (!rescue(fn () => FinanceService::isCourier($user) && !$user->can('crm'), false, false)) {
            return $next($request);
        }
        $route = $request->route();

        return redirect()->to(match ($route?->getName()) {
            'admin.courier.order' => route('kuryer.order', $route->parameter('order')),
            'admin.courier.team' => route('kuryer.team'),
            'admin.courier.team.show' => route('kuryer.team.show', $route->parameter('courier')),
            default => route('kuryer.home'),
        });
    }
}
