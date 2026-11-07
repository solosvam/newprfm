<?php

use App\Console\Commands\ImportOldParfumshopCategory;

use App\Http\Middleware\setLangMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        ImportOldParfumshopCategory::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        // Qısa domen (paf.az) — sessiyadan əvvəl, hər sorğuda: eyni yolu əsas sayta yönləndirir
        $middleware->prepend(\App\Http\Middleware\RedirectShortDomain::class);
        // throttle:N,M — hər route-un öz sayğacı (ThrottlePerRoute)
        $middleware->alias(['throttle' => \App\Http\Middleware\ThrottlePerRoute::class]);
        // Kuryer tətbiqinin splash ekranı göstərildi (JS qoyur, server oxuyur — şifrələnmir)
        $middleware->encryptCookies(except: ['ps_k_splash']);
        // Telegram webhook-u xaricdən gəlir — CSRF tokeni yoxdur (yoxlama gizli başlıqladır)
        $middleware->validateCsrfTokens(except: ['telegram/webhook', 'kuryer/sw-debug']); // sw-debug — MÜVƏQQƏTİ diaqnostika
        // Locale needs the session started by the web middleware group.
        $middleware->web(append: [setLangMiddleware::class, \App\Http\Middleware\LegacyOpencartUrls::class]);
        // Redirect authenticated users away from the login page for their own guard.
        $middleware->redirectUsersTo(function (Request $request) {
            if ($request->is('admin') || $request->is('admin/*')) {
                return route('admin.dashboard');
            }

            return route('home');
        });

        $middleware->redirectGuestsTo(function (Request $request) {
            // Kuryer tətbiqi ayrıca giriş səhifəsi olmadan admin girişindən istifadə edir (girişdən sonra geri qayıdır)
            if ($request->is('admin') || $request->is('admin/*') || $request->is('kuryer') || $request->is('kuryer/*')) {
                return route('admin.login.form');
            }

            return route('front.login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Saytın 404 səhifəsi; admin, anbar portalı (/w/...), JSON və GET olmayan sorğular standart cavabı alır
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson() || !$request->isMethod('GET')
                || $request->is('admin', 'admin/*', 'kuryer', 'kuryer/*', 'w/*', 'warehouse-portal/*')) {
                return null;
            }

            return response()->view('frontend.404', [], 404);
        });
    })->create();
