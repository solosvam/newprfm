<?php

namespace App\Http\Middleware;

use App\Services\FinanceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Kuryer tətbiqi (/kuryer) yalnız kuryer və baş kuryer üçündür; qalan əməkdaşlar admin panelə qaytarılır */
class CourierOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!FinanceService::isCourier($request->user('admin'))) {
            return redirect()->route('admin.dashboard');
        }

        return $next($request);
    }
}
