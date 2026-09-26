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
        // Locale needs the session started by the web middleware group.
        $middleware->web(append: [setLangMiddleware::class]);
        // Redirect authenticated users away from the login page for their own guard.
        $middleware->redirectUsersTo(function (Request $request) {
            if ($request->is('admin') || $request->is('admin/*')) {
                return route('admin.dashboard');
            }

            return route('home');
        });

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('admin') || $request->is('admin/*')) {
                return route('admin.login.form');
            }

            return route('front.login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
