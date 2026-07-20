<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class LanguageFilter implements FilterInterface
{
    public function before(RequestInterface $request, $params = null)
    {
        $session = session();
        $supportedLocales = ['pt-BR', 'en', 'es'];
        $locale = null;

        // 1. Verifica parâmetro GET ?lang=
        $getLang = $request->getGet('lang');
        if ($getLang && in_array($getLang, $supportedLocales, true)) {
            $locale = $getLang;
        }

        // 2. Senão, verifica na Sessão
        if (!$locale && $session->has('lang')) {
            $sessLang = $session->get('lang');
            if (in_array($sessLang, $supportedLocales, true)) {
                $locale = $sessLang;
            }
        }

        // 3. Senão, verifica Cookie
        if (!$locale && isset($_COOKIE['site_lang'])) {
            $cookieLang = $_COOKIE['site_lang'];
            if (in_array($cookieLang, $supportedLocales, true)) {
                $locale = $cookieLang;
            }
        }

        // 4. Fallback padrão: 'pt-BR'
        if (!$locale) {
            $locale = 'pt-BR';
        }

        // Define o locale no Request do CodeIgniter, no serviço de Language e salva na sessão
        $request->setLocale($locale);
        \Config\Services::language()->setLocale($locale);
        $session->set('lang', $locale);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $params = null)
    {
        // Nenhuma ação pós-requisição necessária
    }
}
