<?php

namespace App\Controllers;

use App\Controllers\BaseController;

class SitemapController extends BaseController
{
    public function index()
    {
        $routes = \Config\Services::routes();
        $pages = [];

        foreach ($routes->getRoutes() as $route => $controller) {
            if (!str_contains($route, '(:any)') && !str_contains($route, '(:num)')) { 
                // Ignorar rotas dinâmicas com parâmetros
                $pages[] = [
                    'loc' => base_url($route),
                    'lastmod' => date('Y-m-d')
                ];
            }
        }

        header("Content-Type: application/xml; charset=UTF-8");

        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        foreach ($pages as $page) {
            echo "<url>\n";
            echo "<loc>{$page['loc']}</loc>\n";
            echo "<lastmod>{$page['lastmod']}</lastmod>\n";
            echo "<changefreq>weekly</changefreq>\n";
            echo "<priority>0.8</priority>\n";
            echo "</url>\n";
        }

        echo "</urlset>";
    }
}