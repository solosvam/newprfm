<?php

namespace App\Providers;

use App\Models\Product\Category;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Əlaqə məlumatları bir sorğu ilə oxunur və sorğu boyunca paylaşılır (footer + Əlaqə səhifəsi)
        $this->app->scoped(\App\Services\ContactInfo::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('frontend.partials.subnav', function ($view) {
            $view->with(
                'categories',
                Category::where('active', 1)
                    ->orderBy('id')
                    ->get()
            );
        });
    }
}
