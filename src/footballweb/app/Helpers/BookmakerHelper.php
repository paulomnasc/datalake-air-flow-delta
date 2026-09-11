<?php

namespace App\Helpers {
    class BookmakerHelper {
        public static function getUrl(?string $bmName): string {
            return \getBookmakerUrl($bmName);
        }
    }
}

namespace {
    if (!function_exists('getBookmakerUrl')) {
        /**
         * Retorna a URL oficial da casa de aposta informada, com fallback seguro para https://www.{nome}.com
         *
         * @param string|null $bmName Nome da casa de apostas
         * @return string URL oficial da casa de apostas
         */
        function getBookmakerUrl(?string $bmName): string {
            $bm = strtoupper(trim($bmName ?? ''));
            if (empty($bm)) {
                return 'https://br.betano.com/';
            }

            $urls = [
                'BETANO'       => 'https://br.betano.com/',
                'BET365'       => 'https://www.bet365.com/',
                'PINNACLE'     => 'https://www.pinnacle.com/',
                '1XBET'        => 'https://1xbet.com/',
                'BETFAIR'      => 'https://www.betfair.com/br',
                'SPORTINGBET'  => 'https://www.sportingbet.com/pt-br',
                'SUPERBET'     => 'https://superbet.com/pt-br/',
                'KTO'          => 'https://www.kto.com/pt/',
                'BETNACIONAL'  => 'https://betnacional.com/',
                'NOVIBET'      => 'https://www.novibet.com.br/',
                'STAKE'        => 'https://stake.com/',
                'PARIMATCH'    => 'https://parimatch.com.br/',
                'ESTRELA'      => 'https://estrelabet.com/',
                'RIVALO'       => 'https://www.rivalo.com/pt',
                'GALERA'       => 'https://www.galera.bet/',
                'BLAZE'        => 'https://blaze.com/',
                'UNIBET'       => 'https://www.unibet.com/',
                'CASUMO'       => 'https://www.casumo.com/',
                'GROSVENOR'    => 'https://www.grosvenorcasinos.com/sport',
                'LADBROKES'    => 'https://sports.ladbrokes.com/',
                'BETSSON'      => 'https://www.betsson.com/',
                'COOLBET'      => 'https://www.coolbet.com/',
                '888SPORT'     => 'https://www.888sport.com/',
                'WILLIAM HILL' => 'https://sports.williamhill.com/',
                'BETWAY'       => 'https://www.betway.com/',
                'LEOVEGAS'     => 'https://www.leovegas.com/',
                'PADDY POWER'  => 'https://sports.paddypower.com/',
                'CORAL'        => 'https://sports.coral.co.uk/',
                'VIRGIN'       => 'https://www.virginbet.com/',
                'LIVESCORE'    => 'https://www.livescorebet.com/',
                'WINAMAX'      => 'https://www.winamax.fr/',
                'MARATHON'     => 'https://www.marathonbet.com/',
                'CODERE'       => 'https://www.codere.es/',
                'BETCLIC'      => 'https://www.betclic.fr/',
                'MATCHBOOK'    => 'https://www.matchbook.com/',
                'BETONLINE'    => 'https://www.betonline.ag/',
                'SMARKETS'     => 'https://smarkets.com/'
            ];

            foreach ($urls as $key => $url) {
                if (strpos($bm, $key) !== false) {
                    return $url;
                }
            }

            $cleanDomain = preg_replace('/[^a-z0-9]/', '', strtolower($bmName));
            return 'https://www.' . $cleanDomain . '.com';
        }
    }
}
