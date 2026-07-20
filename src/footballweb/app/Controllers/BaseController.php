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

        // Sincroniza o locale do idioma selecionado pelo usuário
        $currentLang = $this->session->get('lang') ?? ($_COOKIE['site_lang'] ?? 'pt-BR');
        if (in_array($currentLang, ['pt-BR', 'en', 'es'], true)) {
            $request->setLocale($currentLang);
            \Config\Services::language()->setLocale($currentLang);
        }
        // Preload any models, libraries, etc, here.

        // E.g.: $this->session = \Config\Services::session();
    }

    protected function getUserFunctionalities()
    {
        // Neste sistema (fiscalweb) não teremos a entidade perfil_funcionalidade.
        // Vamos retornar true provisoriamente, ou implementar lógica customizada
        // com base no perfil do usuário no futuro.
        
        $userHasBucketsAccess = true;
        $userHasPipelinesAccess = true;
        
        return [
            'userHasBucketsAccess' => $userHasBucketsAccess,
            'userHasPipelinesAccess' => $userHasPipelinesAccess
        ];
    }
    protected function loadView($viewName, $data = [])
    {
        // Aqui você pode registrar ou exibir o nome da view
        log_message('info', "View carregada: " . $viewName);

        // Buscar funcionalidades do usuário e adicionar aos dados da view
        $functionalities = $this->getUserFunctionalities();
        $data['userHasBucketsAccess'] = $functionalities['userHasBucketsAccess'];
        $data['userHasPipelinesAccess'] = $functionalities['userHasPipelinesAccess'];

        if (!isset($data['metaTags'])) {
            $seo = new SeoHelper();
            $seo->setHomePageDefaults();
            $generatedTags = $seo->generateMetaTags();
            $data['metaTags'] = $generatedTags;
            $this->session->set('metaTags', $generatedTags);
        }

        return view($viewName, $data);
    }

    protected function buildExceptionMessage(\Throwable $e): string
    {
        $message = $e->getMessage();

        $previous = $e->getPrevious();
        if ($previous instanceof \Throwable && $previous->getMessage()) {
            $message = $previous->getMessage();
        }

        if (str_contains($message, 'Duplicate entry') || $e->getCode() == 1062) {
            if (str_contains($message, 'servico_unique') || str_contains($message, 'numero_item')) {
                return 'O Nº Item informado já está cadastrado para outro serviço.';
            }
            if (str_contains($message, 'servico_descr_unique_1') || str_contains($message, 'descricao')) {
                return 'A Descrição informada já está cadastrada para outro serviço.';
            }
            return 'Um registro com estes dados já está cadastrado no sistema.';
        }

        return trim($message) ?: 'Erro desconhecido no servidor.';
    }
}
