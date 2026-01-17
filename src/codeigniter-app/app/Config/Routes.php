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
$routes->get('/debugFunctionalities', 'Home::debugFunctionalities', ['as'=>'debugFunctionalities']);



//Perfil
$routes->get('/listPerfil', 'PerfilController::index', ['as'=>'listPerfil']);
$routes->post('/addPerfil', 'PerfilController::add', ['as'=>'addPerfil']); //Exibe a tela de add
$routes->post('/updPerfil', 'PerfilController::upd', ['as'=>'updPerfil']); //Exibe a tela de upd
$routes->post('/insertPerfil', 'PerfilController::insert', ['as'=>'Perfil.insert']);//Executa o método insert da PerfilController
$routes->post('/updatePerfil', 'PerfilController::update', ['as'=>'Perfil.update']);//Executa o método update da PerfilController
$routes->delete('/deletePerfil/(:num)', 'PerfilController::delete/$1', ['as' => 'Perfil.delete']);
// Funcionalidade
$routes->get('/listFuncionalidade', 'FuncionalidadeController::index', ['as' => 'listFuncionalidade']);
$routes->post('/addFuncionalidade', 'FuncionalidadeController::add', ['as' => 'addFuncionalidade']); // Exibe a tela de add
$routes->post('/updFuncionalidade', 'FuncionalidadeController::upd', ['as' => 'updFuncionalidade']); // Exibe a tela de upd
$routes->post('/insertFuncionalidade', 'FuncionalidadeController::insert', ['as' => 'Funcionalidade.insert']); // Executa o método insert da FuncionalidadeController
$routes->post('/updateFuncionalidade', 'FuncionalidadeController::update', ['as' => 'Funcionalidade.update']); // Executa o método update da FuncionalidadeController
$routes->delete('/deleteFuncionalidade/(:num)', 'FuncionalidadeController::delete/$1', ['as' => 'Funcionalidade.delete']);
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

// Debug/Teste MinIO
$routes->get('/test-minio/connection', 'TestMinioController::testConnection', ['as'=>'test.minio.connection']);
$routes->get('/test-minio/upload', 'TestMinioController::testUpload', ['as'=>'test.minio.upload']);

// Query Builder - DuckDB Parquet
$routes->get('/query-builder', 'QueryBuilderController::index', ['as'=>'query-builder.index']);
$routes->post('/query-builder/execute', 'QueryBuilderController::execute', ['as'=>'query-builder.execute']);
$routes->post('/query-builder/tables', 'QueryBuilderController::listTables', ['as'=>'query-builder.tables']);
$routes->post('/query-builder/schema', 'QueryBuilderController::getSchema', ['as'=>'query-builder.schema']);
$routes->get('/query-builder/status', 'QueryBuilderController::status', ['as'=>'query-builder.status']);
$routes->post('/query-builder/parquet-files', 'QueryBuilderController::listParquetFiles', ['as'=>'query-builder.parquet-files']);
$routes->get('/query-builder/debug', 'QueryBuilderController::debug', ['as'=>'query-builder.debug']);

// 🆕 UNIFIED CODE EDITOR - Nova Rota (SQL + Validation Rules)
$routes->get('/unified-code-editor', 'CodeEditorController::unified', ['as'=>'unified-code-editor']);

// Unified Code Editor (SQL + Validation Rules) - NOVA ROTA
$routes->get('/code-editor-unified', 'CodeEditorController::unified', ['as'=>'code-editor.unified']);
$routes->get('/code-editor', 'CodeEditorController::unified', ['as'=>'code-editor.index']);
$routes->get('/validation-rules-editor', 'CodeEditorController::unified', ['as'=>'validation-rules.editor']);

// Code Editor Legacy Routes
$routes->get('/code-editor/status', 'CodeEditorController::status', ['as'=>'code-editor.status']);
$routes->post('/code-editor/execute', 'CodeEditorController::execute', ['as'=>'code-editor.execute']);
$routes->post('/code-editor/tables', 'CodeEditorController::listTables', ['as'=>'code-editor.tables']);
$routes->post('/code-editor/schema', 'CodeEditorController::getSchema', ['as'=>'code-editor.schema']);
$routes->post('/code-editor/files', 'CodeEditorController::listParquetFiles', ['as'=>'code-editor.files']);
$routes->get('/test-git-sidebar', 'CodeEditorController::testGitSidebar', ['as'=>'test-git-sidebar']);

// Validation Rules API endpoints
$routes->get('/api/validation-rules', 'ValidationRulesController::list', ['as'=>'validation-rules.list']);
$routes->post('/api/validation-rule-save', 'ValidationRulesController::save', ['as'=>'validation-rules.save']);
$routes->post('/api/validation-rule-test', 'ValidationRulesController::test', ['as'=>'validation-rules.test']);
$routes->delete('/api/validation-rule-delete', 'ValidationRulesController::delete', ['as'=>'validation-rules.delete']);
$routes->post('/api/validation-deploy', 'ValidationRulesController::deploy', ['as'=>'validation-deploy']);

// SQL API endpoint
$routes->post('/api/query-sql', 'CodeEditorController::querySQL', ['as'=>'api.query-sql']);

// Git CORS Proxy endpoint
$routes->match(['get','post','options'], '/git-proxy.php', 'GitProxyController::index');
$routes->match(['get','post','options'], '/git-proxy', 'GitProxyController::index');


// Git Server-side endpoints (clone no servidor)
$routes->post('/api/git-clone', 'GitServerController::cloneRepository', ['as'=>'api.git.clone']);
$routes->get('/api/git-files', 'GitServerController::listFiles', ['as'=>'api.git.files']);
$routes->get('/api/git-file-content', 'GitServerController::getFileContent', ['as'=>'api.git.file.content']);
$routes->post('/api/git-file-save', 'GitServerController::saveFileContent', ['as'=>'api.git.file.save']);
$routes->post('/api/git-folder-create', 'GitServerController::createFolder', ['as'=>'api.git.folder.create']);
$routes->post('/api/git-entry-rename', 'GitServerController::renameEntry', ['as'=>'api.git.entry.rename']);
$routes->match(['delete', 'post'], '/api/git-file-delete', 'GitServerController::deleteFileContent', ['as'=>'api.git.file.delete']);
$routes->post('/api/git-push', 'GitServerController::gitPush', ['as'=>'api.git.push']);



/* $routes->get('upload', 'UploadController::index');
$routes->post('/fileUpload', 'UploadController::upload',['as'=>'Config.upload']); */

// Rotas do e-commerce ou marketing interface
$routes->get('/contactUs', 'MarketPlaceController::contactUs', ['as'=>'contactUs']);//Exibe a entre em contato conosco
$routes->get('/politica', 'MarketPlaceController::politica', ['as'=>'politica']);//Exibe a tela política de privacidade
$routes->get('/tdu', 'MarketPlaceController::tdu', ['as'=>'tdu']);//Termos de uso
$routes->post('/email', 'MarketPlaceController::sendMailNoSecurity', ['as'=>'email']);//Dipara o email preenchido na tela contactUs 
$routes->post('/saibaMais', 'MarketPlaceController::saibaMais', ['as'=>'saibaMais']);//Dipara o form saiba mais 
$routes->get('/saibaMais', 'MarketPlaceController::saibaMais', ['as'=>'saibaMais']);//Dipara o form saiba mais 

// Rotas de Assinatura/Subscription
$routes->get('/subscription/renew', 'SubscriptionController::index', ['as'=>'subscription.renew']); // Página de renovação
$routes->get('/subscription/status', 'SubscriptionController::checkStatus', ['as'=>'subscription.status']); // Verifica status via AJAX
$routes->post('/subscription/confirmPayment', 'SubscriptionController::confirmPayment', ['as'=>'subscription.confirmPayment']); // Confirma pagamento
$routes->get('/subscription/pix', 'SubscriptionController::pixPayment', ['as'=>'subscription.pix']); // Página PIX
$routes->get('/test-subscription', 'TestSubscription::index'); // DEBUG - Teste de subscription

//Botão Donate$
$routes->post('/donate', 'MarketPlaceController::donate');

//Login com Google
$routes->post('auth/google-login', 'AuthController::googleLogin');


//Misc.
$routes->get('sitemap.xml', 'SitemapController::index');