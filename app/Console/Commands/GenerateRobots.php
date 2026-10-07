<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * public/robots.txt-i yaradır: şəxsi bölmələr bağlı + Sitemap ünvanı (APP_URL-dən).
 * Prod-da APP_URL düzgün təyin olunandan sonra bir dəfə işə salın: php artisan seo:robots
 */
class GenerateRobots extends Command
{
    protected $signature = 'seo:robots';

    protected $description = 'public/robots.txt faylını sitemap ünvanı ilə yaradır';

    public function handle(): int
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /profile',
            'Disallow: /checkout',
            'Disallow: /cart',
            'Disallow: /orders',
            'Disallow: /w/',
            'Disallow: /pay/',
            '',
            'Sitemap: '.rtrim(config('app.url'), '/').'/sitemap.xml',
        ];
        file_put_contents(public_path('robots.txt'), implode("\n", $lines)."\n");
        $this->info('public/robots.txt yaradıldı ('.config('app.url').').');

        return self::SUCCESS;
    }
}
