<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Frontend\LegacyRedirectController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Köhnə OpenCart ünvanları: /index.php?route=...
 * Proddakı veb server index.php-ni fayl kimi işə salır — Laravel belə sorğunu "/" görür və /index.php marşrutuna çatmır
 * (lokalda Valet yolu ötürür). Ona görə ana səhifəyə "?route=" ilə gələn sorğu da köhnə ünvan sayılır.
 */
class LegacyOpencartUrls
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->getPathInfo() === '/' && $request->query->has('route')) {
            return app(LegacyRedirectController::class)($request);
        }

        return $next($request);
    }
}
