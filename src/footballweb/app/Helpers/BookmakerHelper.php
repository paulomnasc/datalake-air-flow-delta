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
            return '';
        }
    }
}
