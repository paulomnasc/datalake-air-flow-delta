<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;
use App\Libraries\SeoHelper;


/**
 * Class BaseController
 *
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 * Extend this class in any new controllers:
 *     class Home extends BaseController
 *
 * For security be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Instance of the main Request object.
     *
     * @var CLIRequest|IncomingRequest
     */
    protected $request;

    /**
     * An array of helpers to be loaded automatically upon
     * class instantiation. These helpers will be available
     * to all other controllers that extend BaseController.
     *
     * @var list<string>
     */
    protected $helpers = [];

    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */
    // protected $session;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);
        session();
        $this->session = \Config\Services::session();
        // Preload any models, libraries, etc, here.

        // E.g.: $this->session = \Config\Services::session();
    }

    protected function loadView($viewName, $data = [])
    {
        // Aqui você pode registrar ou exibir o nome da view
        log_message('info', "View carregada: " . $viewName);

        $seo = new SeoHelper();
        $seo->setTitle("Aprenda Fácil: Concursos, Enem, Provas, Exames com Tabelas Resumo, Jogos de Memória e Técnicas de Estudo");
        $seo->setDescription("Descubra jogos interativos online e eficaz, para estudo, incluindo jogos de memória e técnicas para memorização de conteúdos. Aprenda com tabelas resumo que tornam o estudo mais fácil e divertido!");

        $seo->setKeywords("jogo da memória, jogo educativo, jogo interativo, ensino médio, preparação ENEM, estudo online, quadro sinóptico, mapa mental, aprendizado divertido, concurso público, materiais didáticos");
        
        $seo->setImage(base_url("assets/images/home.jpg"));
        $seo->setUrl(base_url());
        $data['metaTags'] = $seo->generateMetaTags();
        // Salvar na sessão
        $this->session->set('metaTags', $seo->generateMetaTags());

        return view($viewName, $data);
    }

}
