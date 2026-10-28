<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'landing')->name('home');

Route::get('/sitemap.xml', function () {
    $url = rtrim(config('app.url'), '/');
    $pages = [
        ['loc' => $url.'/', 'priority' => '1.0', 'changefreq' => 'weekly'],
        ['loc' => $url.'/merchant/register', 'priority' => '0.8', 'changefreq' => 'monthly'],
        ['loc' => $url.'/merchant/login', 'priority' => '0.3', 'changefreq' => 'yearly'],
    ];
    $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach ($pages as $p) {
        $xml .= "<url><loc>{$p['loc']}</loc><lastmod>".now()->toDateString()."</lastmod><changefreq>{$p['changefreq']}</changefreq><priority>{$p['priority']}</priority></url>";
    }

    return response($xml.'</urlset>', 200, ['Content-Type' => 'application/xml']);
})->name('sitemap');

Route::get('/robots.txt', fn () => response(implode("\n", [
    'User-agent: *',
    'Allow: /',
    'Allow: /merchant/register',
    'Disallow: /admin',
    'Disallow: /merchant/',
    'Disallow: /api/',
    '',
    'Sitemap: '.rtrim(config('app.url'), '/').'/sitemap.xml',
]), 200, ['Content-Type' => 'text/plain']));
