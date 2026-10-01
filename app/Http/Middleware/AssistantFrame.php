<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Assistant səhifəsini yalnız bizim Chrome extension-u iframe-də aça bilər */
class AssistantFrame
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $id = config('services.assistant.extension_id');
        $ancestors = $id ? "'self' chrome-extension://{$id}" : "'self'";

        $response->headers->remove('X-Frame-Options');
        $response->headers->set('Content-Security-Policy', "frame-ancestors {$ancestors}");

        return $response;
    }
}
