<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

class SitemapController extends BaseController
{
    public function index(): ResponseInterface
    {
        helper('url');

        $pages = [];

        // Páginas estáticas principais
        $pages[] = [
            'loc'        => base_url('/'),
            'lastmod'    => date('Y-m-d'),
            'changefreq' => 'daily',
            'priority'   => '1.0'
        ];

        $pages[] = [
            'loc'        => base_url('football-trends'),
            'lastmod'    => date('Y-m-d'),
            'changefreq' => 'daily',
            'priority'   => '0.9'
        ];

        $pages[] = [
            'loc'        => base_url('cursos'),
            'lastmod'    => date('Y-m-d'),
            'changefreq' => 'weekly',
            'priority'   => '0.7'
        ];

        // Partidas dinâmicas registradas na base de dados
        try {
            $db = \Config\Database::connect();
            $builder = $db->table('fixtures_trends');
            $builder->select('fixture_date, home_team, away_team, updated_at');
            $builder->orderBy('fixture_date', 'DESC');
            $builder->limit(1000);
            $fixtures = $builder->get()->getResultObject();

            foreach ($fixtures as $fix) {
                if (empty($fix->home_team) || empty($fix->away_team)) {
                    continue;
                }

                $fixtureDate = !empty($fix->fixture_date) ? date('Y-m-d', strtotime($fix->fixture_date)) : date('Y-m-d');
                $homeSlug = url_title(mb_strtolower($fix->home_team), '-', true);
                $awaySlug = url_title(mb_strtolower($fix->away_team), '-', true);

                $slug = "{$fixtureDate}-{$homeSlug}-x-{$awaySlug}";
                $lastmod = !empty($fix->updated_at) ? date('Y-m-d', strtotime($fix->updated_at)) : $fixtureDate;

                $pages[] = [
                    'loc'        => base_url("jogos/{$slug}"),
                    'lastmod'    => $lastmod,
                    'changefreq' => 'daily',
                    'priority'   => '0.8'
                ];
            }
        } catch (\Exception $e) {
            log_message('error', 'Erro ao carregar jogos no SitemapController: ' . $e->getMessage());
        }

        // Construção do XML do Sitemap
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        foreach ($pages as $page) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($page['loc'], ENT_QUOTES, 'UTF-8') . "</loc>\n";
            $xml .= "    <lastmod>{$page['lastmod']}</lastmod>\n";
            $xml .= "    <changefreq>{$page['changefreq']}</changefreq>\n";
            $xml .= "    <priority>{$page['priority']}</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= "</urlset>";

        return $this->response
            ->setHeader('Content-Type', 'text/xml; charset=UTF-8')
            ->setBody($xml);
    }
}