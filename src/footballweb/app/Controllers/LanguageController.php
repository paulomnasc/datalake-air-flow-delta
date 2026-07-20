<?php

namespace App\Controllers;

use CodeIgniter\Controller;

class LanguageController extends BaseController
{
    public function switchLanguage(string $locale = 'pt-BR')
    {
        $supportedLocales = ['pt-BR', 'en', 'es'];

        if (!in_array($locale, $supportedLocales, true)) {
            $locale = 'pt-BR';
        }

        // Salva idioma na sessão
        session()->set('lang', $locale);

        // Salva idioma em cookie (30 dias)
        setcookie('site_lang', $locale, time() + (86400 * 30), '/');

        // Define o locale do serviço de request e language
        service('request')->setLocale($locale);
        \Config\Services::language()->setLocale($locale);

        // Redireciona de volta para a página de onde o usuário veio
        $referer = $this->request->getServer('HTTP_REFERER');
        if ($referer && strpos($referer, base_url()) === 0) {
            return redirect()->to($referer);
        }

        return redirect()->to(base_url('/'));
    }
}
