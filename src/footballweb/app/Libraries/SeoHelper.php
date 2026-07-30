<?php

namespace App\Libraries;

class SeoHelper
{
    private $title;
    private $description;
    private $keywords;
    private $image;
    private $url;
    private $robots;
    private $ogType;
    private $schemaJsonLd;

    public function __construct()
    {
        $this->setHomePageDefaults();
    }

    /**
     * Configura as Meta Tags padrão institucionais da Home Page
     */
    public function setHomePageDefaults()
    {
        $baseUrl = function_exists('base_url') ? base_url() : 'https://www.cristalbet.com.br/';
        
        $this->title       = "CristalBet | Estatísticas de Futebol e Previsões de Cartões e Escanteios";
        $this->description = "A bola de cristal das estatísticas esportivas. Análise matemática completa de times, médias de escanteios e perfil rigoroso de árbitros para lucrar no mercado de cartões.";
        $this->keywords    = "estatísticas de futebol, média de cartões árbitros, palpites escanteios, estatísticas cartões brasileirão, robô de palpites, cristalbet";
        $this->image       = function_exists('base_url') ? base_url('assets/banner-cristalbet.png') : 'https://www.cristalbet.com.br/assets/banner-cristalbet.png';
        $this->url         = $baseUrl;
        $this->robots      = "index, follow";
        $this->ogType      = "website";
        $this->schemaJsonLd = null;
    }

    /**
     * Configura as Meta Tags dinâmicas e dados estruturados para a Landing Page de Football Trends
     */
    public function setFootballTrendsDefaults(?string $targetDate = null, int $totalFixtures = 0, array $leagues = [])
    {
        $baseUrl = function_exists('base_url') ? base_url() : 'https://www.cristalbet.com.br/';
        $canonicalUrl = rtrim($baseUrl, '/') . '/football-trends';
        
        $formattedDate = !empty($targetDate) ? date('d/m/Y', strtotime($targetDate)) : date('d/m/Y');
        
        $leagueText = !empty($leagues) ? implode(', ', array_slice($leagues, 0, 4)) : 'Brasileirão, Champions League e Ligas Europeias';

        $this->title       = "Tendências de Futebol Hoje ({$formattedDate}) & Estatísticas de Cartões | CristalBet";
        $this->description = "Confira as estatísticas de futebol hoje ({$formattedDate}), médias de cartões de árbitros, escanteios e tendências de partidas da rodada ({$leagueText}). Análise matemática com Grok AI.";
        $this->keywords    = "tendências de futebol hoje, estatísticas futebol virtual, estatísticas cartões brasileirão, média cartões árbitro, palpites escanteios hoje, robô de palpites futebol, cristalbet";
        $this->image       = function_exists('base_url') ? base_url('assets/banner-cristalbet.png') : 'https://www.cristalbet.com.br/assets/banner-cristalbet.png';
        $this->url         = $canonicalUrl;
        $this->robots      = "index, follow";
        $this->ogType      = "website";

        // Gera marcação Schema.org WebPage + BreadcrumbList
        $schema = [
            '@context' => 'https://schema.org',
            '@graph'   => [
                [
                    '@type' => 'WebPage',
                    '@id' => $canonicalUrl,
                    'url' => $canonicalUrl,
                    'name' => "Tendências de Futebol Hoje ({$formattedDate}) & Estatísticas de Cartões",
                    'description' => "Painel de estatísticas de futebol hoje, médias de faltas, cartões por árbitro e escanteios.",
                    'isPartOf' => [
                        '@type' => 'WebSite',
                        'name'  => 'CristalBet',
                        'url'   => $baseUrl
                    ]
                ],
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        [
                            '@type' => 'ListItem',
                            'position' => 1,
                            'name' => 'Início',
                            'item' => $baseUrl
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 2,
                            'name' => 'Tendências de Futebol',
                            'item' => $canonicalUrl
                        ]
                    ]
                ]
            ]
        ];

        $this->schemaJsonLd = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /**
     * Configura as Meta Tags dinâmicas para a página de um jogo específico
     */
    public function setMatchData(string $homeTeam, string $awayTeam, string $refereeName, ?string $matchDate = null, ?string $canonicalUrl = null)
    {
        $baseUrl = function_exists('base_url') ? base_url() : 'https://www.cristalbet.com.br/';
        
        $refereeText = !empty($refereeName) ? $refereeName : 'Não definido';
        
        $this->title       = "Estatísticas de Cartões {$homeTeam} x {$awayTeam} e Árbitro | CristalBet";
        $this->description = "Confira a análise de cartões e escanteios para {$homeTeam} x {$awayTeam}. Média de faltas dos times e estatísticas completas do árbitro {$refereeText} para a rodada.";
        $this->keywords    = "cartoes {$homeTeam} x {$awayTeam}, arbitro {$refereeText} estatisticas, escanteios {$homeTeam}, palpites {$homeTeam} x {$awayTeam}";
        $this->ogType      = "article";
        $this->robots      = "index, follow";
        
        if (!empty($canonicalUrl)) {
            $this->url = $canonicalUrl;
        } else {
            $slugDate = !empty($matchDate) ? date('Y-m-d', strtotime($matchDate)) : date('Y-m-d');
            $slugHome = url_title(mb_strtolower($homeTeam), '-', true);
            $slugAway = url_title(mb_strtolower($awayTeam), '-', true);
            $this->url = rtrim($baseUrl, '/') . "/jogos/{$slugDate}-{$slugHome}-x-{$slugAway}";
        }

        // Gera Schema JSON-LD SportsEvent
        $this->generateSportsEventSchema($homeTeam, $awayTeam, $refereeText, $matchDate);
    }

    /**
     * Gera a marcação de dados estruturados Schema.org do tipo SportsEvent
     */
    public function generateSportsEventSchema(string $homeTeam, string $awayTeam, string $refereeName = '', ?string $matchDate = null, string $stadium = 'Estádio Principal')
    {
        $startDateIso = !empty($matchDate) ? date('c', strtotime($matchDate)) : date('c');

        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'SportsEvent',
            'name'        => "{$homeTeam} vs {$awayTeam}",
            'description' => "Estatísticas de cartões, escanteios e análise do árbitro {$refereeName} para {$homeTeam} x {$awayTeam}.",
            'startDate'   => $startDateIso,
            'location'    => [
                '@type' => 'Place',
                'name'  => $stadium
            ],
            'homeTeam'    => [
                '@type' => 'SportsTeam',
                'name'  => $homeTeam
            ],
            'awayTeam'    => [
                '@type' => 'SportsTeam',
                'name'  => $awayTeam
            ],
            'organizer'   => [
                '@type' => 'Organization',
                'name'  => 'CristalBet',
                'url'   => function_exists('base_url') ? base_url() : 'https://www.cristalbet.com.br/'
            ]
        ];

        $this->schemaJsonLd = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function setDescription($description)
    {
        $this->description = $description;
    }

    public function setKeywords($keywords)
    {
        $this->keywords = $keywords;
    }

    public function setImage($image)
    {
        $this->image = $image;
    }

    public function setUrl($url)
    {
        $this->url = $url;
    }

    public function setRobots($robots)
    {
        $this->robots = $robots;
    }

    public function setOgType($ogType)
    {
        $this->ogType = $ogType;
    }

    public function setCustomSchemaJsonLd($jsonLd)
    {
        $this->schemaJsonLd = $jsonLd;
    }

    public function generateMetaTags(): string
    {
        $titleEsc       = htmlspecialchars($this->title, ENT_QUOTES, 'UTF-8');
        $descriptionEsc = htmlspecialchars($this->description, ENT_QUOTES, 'UTF-8');
        $keywordsEsc    = htmlspecialchars($this->keywords, ENT_QUOTES, 'UTF-8');
        $imageEsc       = htmlspecialchars($this->image, ENT_QUOTES, 'UTF-8');
        $urlEsc         = htmlspecialchars($this->url, ENT_QUOTES, 'UTF-8');
        $robotsEsc      = htmlspecialchars($this->robots ?? 'index, follow', ENT_QUOTES, 'UTF-8');
        $ogTypeEsc      = htmlspecialchars($this->ogType ?? 'website', ENT_QUOTES, 'UTF-8');

        $html = "
            <!-- Meta Tags Básicas e SEO -->
            <title>{$titleEsc}</title>
            <meta name=\"description\" content=\"{$descriptionEsc}\">
            <meta name=\"keywords\" content=\"{$keywordsEsc}\">
            <meta name=\"robots\" content=\"{$robotsEsc}\">
            <link rel=\"canonical\" href=\"{$urlEsc}\">

            <!-- Open Graph / Facebook / WhatsApp -->
            <meta property=\"og:type\" content=\"{$ogTypeEsc}\">
            <meta property=\"og:url\" content=\"{$urlEsc}\">
            <meta property=\"og:title\" content=\"{$titleEsc}\">
            <meta property=\"og:description\" content=\"{$descriptionEsc}\">
            <meta property=\"og:image\" content=\"{$imageEsc}\">

            <!-- Twitter / X -->
            <meta property=\"twitter:card\" content=\"summary_large_image\">
            <meta property=\"twitter:url\" content=\"{$urlEsc}\">
            <meta property=\"twitter:title\" content=\"{$titleEsc}\">
            <meta property=\"twitter:description\" content=\"{$descriptionEsc}\">
            <meta property=\"twitter:image\" content=\"{$imageEsc}\">
        ";

        if (!empty($this->schemaJsonLd)) {
            $html .= "
            <!-- Dados Estruturados Schema.org JSON-LD -->
            <script type=\"application/ld+json\">
            {$this->schemaJsonLd}
            </script>
            ";
        }

        return $html;
    }
}