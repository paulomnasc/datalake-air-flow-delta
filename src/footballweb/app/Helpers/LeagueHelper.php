<?php

namespace App\Helpers;

class LeagueHelper
{
    /**
     * Mapeamento direto de League ID para País, Bandeira Emoji e Popularidade.
     */
    public static array $leagueMap = [
        // Brasil
        71   => ['country' => 'Brasil', 'flag' => '🇧🇷', 'popular' => true],
        72   => ['country' => 'Brasil', 'flag' => '🇧🇷', 'popular' => true],
        73   => ['country' => 'Brasil', 'flag' => '🇧🇷', 'popular' => true],
        74   => ['country' => 'Brasil', 'flag' => '🇧🇷', 'popular' => true],
        75   => ['country' => 'Brasil', 'flag' => '🇧🇷', 'popular' => true],
        76   => ['country' => 'Brasil', 'flag' => '🇧🇷', 'popular' => true],
        // Portugal
        94   => ['country' => 'Portugal', 'flag' => '🇵🇹', 'popular' => true],
        95   => ['country' => 'Portugal', 'flag' => '🇵🇹', 'popular' => false],
        96   => ['country' => 'Portugal', 'flag' => '🇵🇹', 'popular' => false],
        // Inglaterra
        39   => ['country' => 'Inglaterra', 'flag' => '🏴󠁧󠁢󠁥󠁮󠁧󠁿', 'popular' => true],
        40   => ['country' => 'Inglaterra', 'flag' => '🏴󠁧󠁢󠁥󠁮󠁧󠁿', 'popular' => false],
        41   => ['country' => 'Inglaterra', 'flag' => '🏴󠁧󠁢󠁥󠁮󠁧󠁿', 'popular' => false],
        42   => ['country' => 'Inglaterra', 'flag' => '🏴󠁧󠁢󠁥󠁮󠁧󠁿', 'popular' => false],
        45   => ['country' => 'Inglaterra', 'flag' => '🏴󠁧󠁢󠁥󠁮󠁧󠁿', 'popular' => false],
        48   => ['country' => 'Inglaterra', 'flag' => '🏴󠁧󠁢󠁥󠁮󠁧󠁿', 'popular' => false],
        // Espanha
        140  => ['country' => 'Espanha', 'flag' => '🇪🇸', 'popular' => true],
        141  => ['country' => 'Espanha', 'flag' => '🇪🇸', 'popular' => false],
        143  => ['country' => 'Espanha', 'flag' => '🇪🇸', 'popular' => false],
        // Itália
        135  => ['country' => 'Itália', 'flag' => '🇮🇹', 'popular' => true],
        136  => ['country' => 'Itália', 'flag' => '🇮🇹', 'popular' => false],
        137  => ['country' => 'Itália', 'flag' => '🇮🇹', 'popular' => false],
        // Alemanha
        78   => ['country' => 'Alemanha', 'flag' => '🇩🇪', 'popular' => true],
        79   => ['country' => 'Alemanha', 'flag' => '🇩🇪', 'popular' => false],
        81   => ['country' => 'Alemanha', 'flag' => '🇩🇪', 'popular' => false],
        // França
        61   => ['country' => 'França', 'flag' => '🇫🇷', 'popular' => true],
        62   => ['country' => 'França', 'flag' => '🇫🇷', 'popular' => false],
        66   => ['country' => 'França', 'flag' => '🇫🇷', 'popular' => false],
        // Holanda
        88   => ['country' => 'Holanda', 'flag' => '🇳🇱', 'popular' => true],
        89   => ['country' => 'Holanda', 'flag' => '🇳🇱', 'popular' => false],
        90   => ['country' => 'Holanda', 'flag' => '🇳🇱', 'popular' => false],
        // México
        262  => ['country' => 'México', 'flag' => '🇲🇽', 'popular' => true],
        263  => ['country' => 'México', 'flag' => '🇲🇽', 'popular' => false],
        // Argentina
        128  => ['country' => 'Argentina', 'flag' => '🇦🇷', 'popular' => true],
        129  => ['country' => 'Argentina', 'flag' => '🇦🇷', 'popular' => false],
        130  => ['country' => 'Argentina', 'flag' => '🇦🇷', 'popular' => false],
        // EUA
        253  => ['country' => 'EUA', 'flag' => '🇺🇸', 'popular' => true],
        254  => ['country' => 'EUA', 'flag' => '🇺🇸', 'popular' => false],
        // Suécia / Noruega / Finlândia / Romênia / Sérvia / Peru / Equador / Uruguai / Chile / Colômbia
        113  => ['country' => 'Suécia', 'flag' => '🇸🇪', 'popular' => true],
        114  => ['country' => 'Suécia', 'flag' => '🇸🇪', 'popular' => false],
        103  => ['country' => 'Noruega', 'flag' => '🇳🇴', 'popular' => true],
        104  => ['country' => 'Noruega', 'flag' => '🇳🇴', 'popular' => false],
        244  => ['country' => 'Finlândia', 'flag' => '🇫🇮', 'popular' => false],
        283  => ['country' => 'Romênia', 'flag' => '🇷🇴', 'popular' => false],
        286  => ['country' => 'Sérvia', 'flag' => '🇷🇸', 'popular' => false],
        281  => ['country' => 'Peru', 'flag' => '🇵🇪', 'popular' => false],
        242  => ['country' => 'Equador', 'flag' => '🇪🇨', 'popular' => false],
        917  => ['country' => 'Equador', 'flag' => '🇪🇨', 'popular' => false],
        268  => ['country' => 'Uruguai', 'flag' => '🇺🇾', 'popular' => false],
        265  => ['country' => 'Chile', 'flag' => '🇨🇱', 'popular' => false],
        239  => ['country' => 'Colômbia', 'flag' => '🇨🇴', 'popular' => false],
        169  => ['country' => 'China', 'flag' => '🇨🇳', 'popular' => false],
        292  => ['country' => 'Coreia do Sul', 'flag' => '🇰🇷', 'popular' => false],
        98   => ['country' => 'Japão', 'flag' => '🇯🇵', 'popular' => false],
        307  => ['country' => 'Arábia Saudita', 'flag' => '🇸🇦', 'popular' => false],
        203  => ['country' => 'Turquia', 'flag' => '🇹🇷', 'popular' => false],
        207  => ['country' => 'Suíça', 'flag' => '🇨🇭', 'popular' => false],
        144  => ['country' => 'Bélgica', 'flag' => '🇧🇪', 'popular' => false],
        119  => ['country' => 'Dinamarca', 'flag' => '🇩🇰', 'popular' => false],
        121  => ['country' => 'Dinamarca', 'flag' => '🇩🇰', 'popular' => false],
        218  => ['country' => 'Áustria', 'flag' => '🇦🇹', 'popular' => false],
        197  => ['country' => 'Grécia', 'flag' => '🇬🇷', 'popular' => false],
        106  => ['country' => 'Polônia', 'flag' => '🇵🇱', 'popular' => false],
        345  => ['country' => 'República Tcheca', 'flag' => '🇨🇿', 'popular' => false],
        501  => ['country' => 'Paraguai', 'flag' => '🇵🇾', 'popular' => false],
        // Competições Internacionais
        1    => ['country' => 'INTERNACIONAL', 'flag' => '🌍', 'popular' => false],
        2    => ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => false],
        3    => ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => false],
        4    => ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => false],
        5    => ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => false],
        9    => ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => true],
        10   => ['country' => 'INTERNACIONAL', 'flag' => '🌍', 'popular' => true],
        11   => ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => true],
        13   => ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => true],
    ];

    /**
     * Resolve o país, bandeira e popularidade de uma liga por ID ou Nome.
     */
    public static function resolveCountryAndFlag(?int $leagueId, ?string $leagueName): array
    {
        $lId = (int)($leagueId ?? 0);

        if ($lId > 0 && isset(self::$leagueMap[$lId])) {
            return [
                'country' => self::$leagueMap[$lId]['country'],
                'flag'    => self::$leagueMap[$lId]['flag'],
                'popular' => self::$leagueMap[$lId]['popular'] ?? false
            ];
        }

        $lNameLower = strtolower($leagueName ?? '');

        if (empty($lNameLower)) {
            return ['country' => 'Outro', 'flag' => '🌐', 'popular' => false];
        }

        // 1. CONMEBOL / Sul-Americana
        if (
            strpos($lNameLower, 'libertadores') !== false ||
            strpos($lNameLower, 'sudamericana') !== false ||
            strpos($lNameLower, 'recopa') !== false ||
            strpos($lNameLower, 'conmebol') !== false ||
            strpos($lNameLower, 'copa america') !== false
        ) {
            return ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => true];
        }

        // 2. Torneios Internacionais
        if (
            strpos($lNameLower, 'champions league') !== false ||
            strpos($lNameLower, 'europa league') !== false ||
            strpos($lNameLower, 'conference league') !== false ||
            strpos($lNameLower, 'world cup') !== false ||
            strpos($lNameLower, 'copa do mundo') !== false ||
            strpos($lNameLower, 'friendlies') !== false ||
            strpos($lNameLower, 'amistoso') !== false ||
            strpos($lNameLower, 'leagues cup') !== false ||
            strpos($lNameLower, 'nations league') !== false ||
            strpos($lNameLower, 'euro') !== false ||
            strpos($lNameLower, 'concacaf') !== false ||
            strpos($lNameLower, 'afc') !== false ||
            strpos($lNameLower, 'caf') !== false ||
            strpos($lNameLower, 'uefa') !== false ||
            strpos($lNameLower, 'internacional') !== false ||
            strpos($lNameLower, 'international') !== false
        ) {
            return ['country' => 'INTERNACIONAL', 'flag' => '🌍', 'popular' => false];
        }

        // 3. Países por palavras-chave
        if (
            strpos($lNameLower, 'brasil') !== false || strpos($lNameLower, 'brasileirão') !== false ||
            strpos($lNameLower, 'brasileirao') !== false || strpos($lNameLower, 'copa do brasil') !== false ||
            strpos($lNameLower, 'paulista') !== false || strpos($lNameLower, 'carioca') !== false ||
            strpos($lNameLower, 'gaúcho') !== false || strpos($lNameLower, 'gaucho') !== false ||
            strpos($lNameLower, 'mineiro') !== false || strpos($lNameLower, 'baiano') !== false ||
            strpos($lNameLower, 'pernambucano') !== false || strpos($lNameLower, 'cearense') !== false ||
            strpos($lNameLower, 'paranaense') !== false || strpos($lNameLower, 'catarinense') !== false
        ) {
            return ['country' => 'Brasil', 'flag' => '🇧🇷', 'popular' => true];
        }
        if (strpos($lNameLower, 'primeira') !== false || strpos($lNameLower, 'portugal') !== false) {
            return ['country' => 'Portugal', 'flag' => '🇵🇹', 'popular' => true];
        }
        if (strpos($lNameLower, 'england') !== false || strpos($lNameLower, 'premier league') !== false || strpos($lNameLower, 'championship') !== false || strpos($lNameLower, 'league one') !== false || strpos($lNameLower, 'league two') !== false) {
            return ['country' => 'Inglaterra', 'flag' => '🏴󠁧󠁢󠁥󠁮󠁧󠁿', 'popular' => true];
        }
        if (strpos($lNameLower, 'espanha') !== false || strpos($lNameLower, 'spain') !== false || strpos($lNameLower, 'la liga') !== false || strpos($lNameLower, 'segunda divisi') !== false) {
            return ['country' => 'Espanha', 'flag' => '🇪🇸', 'popular' => true];
        }
        if (strpos($lNameLower, 'itália') !== false || strpos($lNameLower, 'italia') !== false || strpos($lNameLower, 'coppa italia') !== false || strpos($lNameLower, 'serie a') !== false || strpos($lNameLower, 'serie b') !== false) {
            return ['country' => 'Itália', 'flag' => '🇮🇹', 'popular' => true];
        }
        if (strpos($lNameLower, 'bundesliga') !== false || strpos($lNameLower, 'alemanha') !== false || strpos($lNameLower, 'germany') !== false) {
            return ['country' => 'Alemanha', 'flag' => '🇩🇪', 'popular' => true];
        }
        if (strpos($lNameLower, 'frança') !== false || strpos($lNameLower, 'france') !== false || strpos($lNameLower, 'ligue 1') !== false || strpos($lNameLower, 'ligue 2') !== false) {
            return ['country' => 'França', 'flag' => '🇫🇷', 'popular' => true];
        }
        if (strpos($lNameLower, 'eredivisie') !== false || strpos($lNameLower, 'holanda') !== false || strpos($lNameLower, 'eerste divisie') !== false) {
            return ['country' => 'Holanda', 'flag' => '🇳🇱', 'popular' => true];
        }
        if (strpos($lNameLower, 'argentina') !== false || strpos($lNameLower, 'liga profesional') !== false || strpos($lNameLower, 'primera nacional') !== false) {
            return ['country' => 'Argentina', 'flag' => '🇦🇷', 'popular' => true];
        }
        if (strpos($lNameLower, 'mls') !== false || strpos($lNameLower, 'major league') !== false || strpos($lNameLower, 'usa') !== false) {
            return ['country' => 'EUA', 'flag' => '🇺🇸', 'popular' => true];
        }
        if (strpos($lNameLower, 'méxico') !== false || strpos($lNameLower, 'mexico') !== false || strpos($lNameLower, 'liga mx') !== false) {
            return ['country' => 'México', 'flag' => '🇲🇽', 'popular' => true];
        }

        return ['country' => 'Outro', 'flag' => '🌐', 'popular' => false];
    }
}

if (!function_exists('resolveLeagueCountryAndFlag')) {
    function resolveLeagueCountryAndFlag($leagueId, $leagueName, $leagueMap = null) {
        return \App\Helpers\LeagueHelper::resolveCountryAndFlag((int)$leagueId, (string)$leagueName);
    }
}
