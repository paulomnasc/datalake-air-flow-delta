<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

class UploadController extends BaseController
{
    public function index()
    {
        //
    }

    public function upload()
    {
        
        try {

            $file = $this->request->getFile('arquivo');
        

            if ($file->isValid() && !$file->hasMoved()) {
                $ext = $file->getClientExtension();
    
                // Verificar se é um arquivo CSV
                if ($ext !== 'csv') {
                    return 'Desculpe, apenas arquivos CSV são permitidos.';
                }
    
                // Mover o arquivo para o diretório de upload
                $file->move(WRITEPATH . 'uploads');
    
            }    
            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'O arquivo ' . $file->getName() . ' foi enviado com sucesso.'
            ]);

        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao inserir o arquivo: ' . $e->getMessage()
            ]);
        }

        
    }
}
