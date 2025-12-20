<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
/* 
Explicação da linha a seguir em $routes->get
GET \Controller\Home.php :: \index.php, por sua vez index.php faz:

public function index(): string
    {
        return view('welcome_message');
    }

Ou seja a sintaxe é: NomedaController:: método da Controller
    */ 
$routes->get('/', 'Home::index', ['as'=>'home']);
$routes->post('/', 'Home::index', ['as'=>'home']);



//Perfil
$routes->get('/listPerfil', 'PerfilController::index', ['as'=>'listPerfil']);
$routes->post('/addPerfil', 'PerfilController::add', ['as'=>'addPerfil']); //Exibe a tela de add
$routes->post('/updPerfil', 'PerfilController::upd', ['as'=>'updPerfil']); //Exibe a tela de upd
$routes->post('/insertPerfil', 'PerfilController::insert', ['as'=>'Perfil.insert']);//Executa o método insert da PerfilController
$routes->post('/updatePerfil', 'PerfilController::update', ['as'=>'Perfil.update']);//Executa o método update da PerfilController
$routes->delete('/deletePerfil/(:num)', 'PerfilController::delete/$1', ['as' => 'Perfil.delete']);
//Usuário
$routes->get('/listUsuario', 'UsuarioController::index', ['as'=>'listUsuario']);
$routes->post('/addUsuario', 'UsuarioController::add', ['as'=>'addUsuario']); //Exibe a tela de add
$routes->post('/updUsuario', 'UsuarioController::upd', ['as'=>'updUsuario']); //Exibe a tela de upd
$routes->post('/insertUsuario', 'UsuarioController::insert', ['as'=>'Usuario.insert']);//Executa o método insert da UsuarioController
$routes->post('/updateUsuario', 'UsuarioController::update', ['as'=>'Usuario.update']);//Executa o método update da UsuarioController
$routes->delete('/deleteUsuario/(:num)', 'UsuarioController::delete/$1', ['as' => 'Usuario.delete']);

$routes->get('/loginUsuario', 'UsuarioController::login', ['as'=>'Usuario.login']);//Navega para a tela de login de usuário
$routes->post('/logarUsuario', 'UsuarioController::logar', ['as'=>'Usuario.logar']);//Efetua o processo de logar do usuário
$routes->get('/logOutUsuario', 'UsuarioController::logOut', ['as'=>'Usuario.logOut']);//Efetua o processo de logoff do usuário
$routes->get('/sigInUsuario', 'UsuarioController::signIn', ['as'=>'sigInUsuario']); //Exibe a tela de add
$routes->post('/insertSigIn', 'UsuarioController::insertSigIn', ['as'=>'Usuario.insertSigIn']);//Executa o método insertSigIn da UsuarioController
$routes->get('/recriaSenha', 'UsuarioController::recriaSenha', ['as'=>'Usuario.recriaSenha']);//Executa o método recriaSenha() da UsuarioController
$routes->post('/salvaRecriaSenha', 'UsuarioController::recuperaUsuarioPorEmail', ['as'=>'Usuario.salvaRecriaSenha']);//Executa o método recriaSenha() da UsuarioController

//login de usuario anonimo
$routes->get('/logarUsuarioAnonimo', 'UsuarioController::logarUsuarioAnonimo', ['as'=>'Usuario.logarUsuarioAnonimo']);//Efetua o processo de logar do usuário

//Confirma token de SingUp de Usuário
$routes->get('confirmEmail', 'UsuarioController::confirmEmail');


//Pastas
$routes->get('/listPasta', 'PastaController::index', ['as'=>'listPasta']);
$routes->post('/addPasta', 'PastaController::add', ['as'=>'addPasta']); //Exibe a tela de add
$routes->post('/updPasta', 'PastaController::upd', ['as'=>'updPasta']); //Exibe a tela de upd
$routes->post('/insertPasta', 'PastaController::insert', ['as'=>'Pasta.insert']);//Executa o método insert da PastaController
$routes->post('/updatePasta', 'PastaController::update', ['as'=>'Pasta.update']);//Executa o método update da PastaController
$routes->delete('/deletePasta/(:num)', 'PastaController::delete/$1', ['as' => 'Pasta.delete']);

//Configs
$routes->get('/listConfig', 'ConfigController::index', ['as'=>'listConfig']);//Exibe a tela listConfig
$routes->post('/addConfig', 'ConfigController::add', ['as'=>'addConfig']); //Exibe a tela de add
$routes->post('/updConfig', 'ConfigController::upd', ['as'=>'updConfig']); //Exibe a tela de upd
$routes->post('/insertConfig', 'ConfigController::insert', ['as'=>'Config.insert']);//Executa o método insert da ConfigController
$routes->post('/updateConfig', 'ConfigController::update', ['as'=>'Config.update']);//Executa o método update da ConfigController
$routes->delete('/deleteConfig/(:num)', 'ConfigController::delete/$1', ['as' => 'Config.delete']);
$routes->get('/listarConfig', 'ConfigController::listarConfig', ['as'=>'listarConfig']);//Preenche o datatable após a pasta ser selecionada na tela
$routes->post('/fileUpload', 'ConfigController::upload', ['as'=>'Config.fileUpload']);//Executa o método insert da UsuarioController
$routes->post('/config/upload-multiple', 'ConfigController::uploadMultipleFiles', ['as'=>'Config.uploadMultiple']);//Processa upload múltiplo de arquivos
$routes->post('/config/getAvailableTables', 'ConfigController::getAvailableTables', ['as'=>'Config.getAvailableTables']);//Busca tabelas disponíveis no banco MySQL
//$routes->post('/salvarTabela', 'ConfigController::salvarTabela', ['as'=>'Config.salvarTabela']);//Salvar Tabela Handsontable


$routes->get('/playPorId/(:num)', 'ConfigController::playPorId/$1', ['as' => 'Config.playPorId']);



/* $routes->get('upload', 'UploadController::index');
$routes->post('/fileUpload', 'UploadController::upload',['as'=>'Config.upload']); */

// Rotas do e-commerce ou marketing interface
$routes->get('/contactUs', 'MarketPlaceController::contactUs', ['as'=>'contactUs']);//Exibe a entre em contato conosco
$routes->get('/politica', 'MarketPlaceController::politica', ['as'=>'politica']);//Exibe a tela política de privacidade
$routes->get('/tdu', 'MarketPlaceController::tdu', ['as'=>'tdu']);//Termos de uso
$routes->post('/email', 'MarketPlaceController::sendMailNoSecurity', ['as'=>'email']);//Dipara o email preenchido na tela contactUs 
$routes->post('/saibaMais', 'MarketPlaceController::saibaMais', ['as'=>'saibaMais']);//Dipara o form saiba mais 
$routes->get('/saibaMais', 'MarketPlaceController::saibaMais', ['as'=>'saibaMais']);//Dipara o form saiba mais 

//Botão Donate$
$routes->post('/donate', 'MarketPlaceController::donate');

//Login com Google
$routes->post('auth/google-login', 'AuthController::googleLogin');


//Misc.
$routes->get('sitemap.xml', 'SitemapController::index');