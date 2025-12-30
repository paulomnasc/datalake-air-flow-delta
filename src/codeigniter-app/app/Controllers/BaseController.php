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

    /**
     * Busca funcionalidades do usuário logado
     */
    protected function getUserFunctionalities()
    {
        $userHasBucketsAccess = false;
        $userHasPipelinesAccess = false;
        
        if (isset($_SESSION['id_usuario_logado']) && !empty($_SESSION['id_usuario_logado'])) {
            try {
                $idUser = $_SESSION['id_usuario_logado'];
                log_message('debug', "getUserFunctionalities: ID do usuário = {$idUser}");
                
                $usuarioPerfilModel = new \App\Models\UsuarioPerfilModel();
                $perfilFuncionalidadeModel = new \App\Models\PerfilFuncionalidadeModel();
                
                // Buscar perfis do usuário
                $perfisUsuario = $usuarioPerfilModel->getPerfisUsuario($idUser);
                log_message('debug', "getUserFunctionalities: Perfis encontrados = " . count($perfisUsuario));
                
                if (!empty($perfisUsuario)) {
                    $funcionalidadesBuckets = ['Visualizar Buckets', 'Criar Buckets', 'Editar Buckets', 'Deletar Buckets'];
                    $funcionalidadesPipelines = ['Operar Fluxos de Dados'];
                    
                    // Verificar funcionalidades para cada perfil do usuário
                    foreach ($perfisUsuario as $perfil) {
                        log_message('debug', "getUserFunctionalities: Verificando perfil ID = {$perfil->id_perfil}");
                        
                        $funcionalidadesPerfil = $perfilFuncionalidadeModel->getFuncionalidadesPerfil($perfil->id_perfil);
                        log_message('debug', "getUserFunctionalities: Funcionalidades do perfil = " . count($funcionalidadesPerfil));
                        
                        foreach ($funcionalidadesPerfil as $func) {
                            log_message('debug', "getUserFunctionalities: Funcionalidade = {$func->funcionalidade_descricao}");
                            
                            if (in_array($func->funcionalidade_descricao, $funcionalidadesBuckets)) {
                                $userHasBucketsAccess = true;
                                log_message('debug', "getUserFunctionalities: Acesso a Buckets concedido");
                            }
                            if (in_array($func->funcionalidade_descricao, $funcionalidadesPipelines)) {
                                $userHasPipelinesAccess = true;
                                log_message('debug', "getUserFunctionalities: Acesso a Pipelines concedido");
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                log_message('error', 'Erro ao buscar funcionalidades do usuário: ' . $e->getMessage());
            }
        } else {
            log_message('debug', "getUserFunctionalities: Nenhum usuário logado");
        }
        
        log_message('debug', "getUserFunctionalities: Final - Buckets={$userHasBucketsAccess}, Pipelines={$userHasPipelinesAccess}");
        
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

        $seo = new SeoHelper();
        $seo->setTitle("MyDataFlow");
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
