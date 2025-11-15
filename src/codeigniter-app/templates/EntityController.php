<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\<%entity%>Model;

class <%entity%>Controller extends BaseController
{
    public function index()
    {
        //

        
        $list = $this->list();        
        return view('list<%entity%>',['list'=>$list]);


    }


    public function add()
    {

        return view('add<%entity%>');

    }

    public function upd()
    {

        $id = $this->request->getPost('id');
        
        $model = new <%entity%>Model();
        $<%entity%> = $model->find($id);

        $data = [
            'id' => $<%entity%>->id,
            '<%entity.field%>' => $<%entity%>-><%entity.field%>
        ];

        return view('upd<%entity%>',$data);

    }

    public function del($id)
    {

        $this->delete($id);
        

    }


    public $<%entity%>is = array(); function list()  {
        
        $model = new <%entity%>Model();
        $list = $model->findAll();
        return $list;
    }

    public function findById(int $id )  {
        
        $model = new <%entity%>Model();
        $<%entity%> = $model->find($id);
        return $<%entity%>;
    }

    public function insert() {
        $data = [
            '<%entity.field%>' => $this->request->getPost('<%entity.field%>')
        ];
        $model = new <%entity%>Model();
        $inserted = $model->insert($data);
    
        return $this->response->setJSON([
            'status' => $inserted ? 'success' : 'warning',
            'mensagem' => $inserted ? 'Registro inserido com sucesso!' : 'Falha ao inserir o registro. Tente novamente.'
        ]);
    }
    
    public function update() {
        $model = new <%entity%>Model();
        $id = $this->request->getPost('id');
        $data = [
            '<%entity.field%>' => $this->request->getPost('<%entity.field%>')
        ];
        $updated = $model->update($id, $data);
    
        return $this->response->setJSON([
            'status' => $updated ? 'success' : 'warning',
            'mensagem' => $updated ? 'Registro atualizado com sucesso!' : 'Falha ao atualizar o registro. Tente novamente.'
        ]);
        
    }
    
    public function delete($id)  {
        
        $model = new <%entity%>Model();
        $deleted = $model->delete($id);
        

        if($deleted) 
        {
            return $this->response->setJSON([
                'status' => $deleted ? 'success' : 'warning',
                'mensagem' => $deleted ? 'Registro atualizado com sucesso!' : 'Falha ao atualizar o registro. Tente novamente.'
            ]);

        }
    }
}
