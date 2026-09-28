<?php

if (! function_exists('asset_v')) {
    /**
     * Lokal CSS/JS faylının URL-inə dəyişmə vaxtını əlavə edir:
     * frontend/css/pages/cart.css → https://.../frontend/css/pages/cart.css?v=1790579872
     */
    function asset_v(string $path): string
    {
        static $cache = [];

        $path = ltrim($path, '/');

        if (! isset($cache[$path])) {
            $file = public_path($path);
            $cache[$path] = asset($path) . (is_file($file) ? '?v=' . filemtime($file) : '');
        }

        return $cache[$path];
    }
}
