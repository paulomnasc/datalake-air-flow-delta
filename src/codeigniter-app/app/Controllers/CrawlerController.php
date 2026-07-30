<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\CrawlerCategoriaModel;
use App\Models\CrawlerUrlModel;
use CodeIgniter\HTTP\ResponseInterface;

class CrawlerController extends BaseController
{
    protected $categoriaModel;
    protected $urlModel;

    public function __construct()
    {
        $this->categoriaModel = new CrawlerCategoriaModel();
        $this->urlModel = new CrawlerUrlModel();
    }

    /**
     * Exibe a listagem de categorias e suas respectivas URLs.
     */
    public function index()
    {
        if (!isset($_SESSION['id_usuario_logado'])) {
            return redirect()->to(route_to('Usuario.login'));
        }

        $categorias = $this->categoriaModel->orderBy('nome', 'ASC')->findAll();
        
        // Carrega as URLs para cada categoria
        foreach ($categorias as $cat) {
            $cat->urls = $this->urlModel->where('categoria_id', $cat->id)->orderBy('url', 'ASC')->findAll();
        }

        return view('crawler/index', [
            'categorias' => $categorias,
            'title' => 'Gerenciamento de URLs do Crawler'
        ]);
    }

    /**
     * Adiciona uma nova categoria (nicho).
     */
    public function addCategory()
    {
        if (!isset($_SESSION['id_usuario_logado'])) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Não autorizado'])->setStatusCode(401);
        }

        $nome = $this->request->getPost('nome');
        
        // Normaliza o nome da categoria para minúsculo para facilitar a busca do crawler
        $nomeNormalizado = trim(mb_strtolower($nome));

        $data = ['nome' => $nomeNormalizado];

        if (!$this->categoriaModel->insert($data)) {
            $errors = $this->categoriaModel->errors();
            return $this->response->setJSON([
                'status' => 'error',
                'message' => implode(' ', $errors)
            ]);
        }

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Categoria cadastrada com sucesso!'
        ]);
    }

    /**
     * Adiciona uma nova URL a uma categoria específica.
     */
    public function addUrl()
    {
        if (!isset($_SESSION['id_usuario_logado'])) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Não autorizado'])->setStatusCode(401);
        }

        $categoriaId = $this->request->getPost('categoria_id');
        $url = trim($this->request->getPost('url'));

        $data = [
            'categoria_id' => $categoriaId,
            'url' => $url
        ];

        if (!$this->urlModel->insert($data)) {
            $errors = $this->urlModel->errors();
            return $this->response->setJSON([
                'status' => 'error',
                'message' => implode(' ', $errors)
            ]);
        }

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'URL cadastrada com sucesso!'
        ]);
    }

    /**
     * Deleta uma categoria inteira (deleta URLs em cascata via DB).
     */
    public function deleteCategory($id)
    {
        if (!isset($_SESSION['id_usuario_logado'])) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Não autorizado'])->setStatusCode(401);
        }

        if ($this->categoriaModel->delete($id)) {
            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'Categoria e suas URLs associadas foram excluídas com sucesso.'
            ]);
        }

        return $this->response->setJSON([
            'status' => 'error',
            'message' => 'Falha ao excluir a categoria.'
        ]);
    }

    /**
     * Deleta uma URL específica.
     */
    public function deleteUrl($id)
    {
        if (!isset($_SESSION['id_usuario_logado'])) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Não autorizado'])->setStatusCode(401);
        }

        if ($this->urlModel->delete($id)) {
            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'URL excluída com sucesso.'
            ]);
        }

        return $this->response->setJSON([
            'status' => 'error',
            'message' => 'Falha ao excluir a URL.'
        ]);
    }
}
