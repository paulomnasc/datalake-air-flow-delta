<?php

use CodeIgniter\Router\RouteCollection;

// Curso - Módulo 1 (Legacy)
$routes->get('/curso/modulo1', 'CursoController::modulo1', ['as'=>'cursoModulo1']);

// Curso - Download de materiais via writable (seguro)
$routes->get('/curso/download/(.*)', 'CursoController::downloadMaterial/$1', ['as'=>'curso.download-material']);

// 🆕 UC Progress Monitor - Tracking de Vídeo e Tarefas
$routes->get('/curso/progress-monitor', 'CursoController::progressMonitor', ['as'=>'curso.progress-monitor']);

// 🎓 Student Course Interface
$routes->get('/cursos', 'CursoController::index', ['as'=>'cursos.index']);
$routes->get('/curso/(:num)', 'CursoController::course/$1', ['as'=>'curso.show']);
$routes->get('/modulo/(:num)', 'CursoController::module/$1', ['as'=>'modulo.show']);
$routes->get('/video/(:num)', 'CursoController::video/$1', ['as'=>'video.player']);
$routes->post('/api/video-progress', 'Api\ProgressController::videoProgress', ['as'=>'api.video-progress']);
$routes->post('/api/uc-progress', 'Api\ProgressController::ucProgress', ['as'=>'api.uc-progress']);
$routes->post('/api/video-feedback', 'Api\ProgressController::saveVideoFeedback', ['as'=>'api.video-feedback']);
$routes->get('/api/video-progress/(:segment)/(:segment)', 'Api\ProgressController::getVideoProgress/$1/$2', ['as'=>'api.video-progress.get']);

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
$routes->get('/getTableSelections/(:num)', 'ConfigController::getTableSelections/$1', ['as'=>'Config.getTableSelections']);
$routes->post('/fileUpload', 'ConfigController::upload', ['as'=>'Config.fileUpload']);//Executa o método insert da UsuarioController
$routes->post('/config/upload-multiple', 'ConfigController::uploadMultipleFiles', ['as'=>'Config.uploadMultiple']);//Processa upload múltiplo de arquivos
$routes->post('/config/getAvailableTables', 'ConfigController::getAvailableTables', ['as'=>'Config.getAvailableTables']);//Busca tabelas disponíveis no banco MySQL
//$routes->post('/salvarTabela', 'ConfigController::salvarTabela', ['as'=>'Config.salvarTabela']);//Salvar Tabela Handsontable


$routes->get('/playPorId/(:num)', 'ConfigController::playPorId/$1', ['as' => 'Config.playPorId']);

// 🆕 NOVA UX - Dashboard com Wizard
$routes->get('/dashboard', 'DashboardController::index', ['as'=>'dashboard']);//Nova interface UX
$routes->get('/dashboard/stats', 'DashboardController::getStatsJson', ['as'=>'dashboard.stats']);//API de estatísticas
$routes->post('/dashboard/createPipeline', 'DashboardController::createPipeline', ['as'=>'dashboard.createPipeline']);//Criar pipeline via wizard
$routes->post('/dashboard/saveDraft', 'DashboardController::saveDraft', ['as'=>'dashboard.saveDraft']);//Salvar rascunho
$routes->get('/dashboard/downloadTemplate/(:segment)/(:segment)', 'DashboardController::downloadTemplate/$1/$2', ['as'=>'dashboard.downloadTemplate']);//Download de template exemplo
$routes->get('/wizard/create-pipeline', 'DashboardController::wizardCreatePipeline', ['as'=>'wizard.create-pipeline']);
$routes->get('/wizard/monaco-iframe', 'DashboardController::wizardMonacoIframe', ['as'=>'wizard.monaco-iframe']);

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
$routes->post('/code-editor/execute-python', 'CodeEditorController::executePython', ['as'=>'code-editor.execute-python']);
$routes->post('/code-editor/tables', 'CodeEditorController::listTables', ['as'=>'code-editor.tables']);
$routes->post('/code-editor/schema', 'CodeEditorController::getSchema', ['as'=>'code-editor.schema']);
$routes->post('/code-editor/files', 'CodeEditorController::listParquetFiles', ['as'=>'code-editor.files']);
$routes->post('/code-editor/delete-files', 'CodeEditorController::deleteFiles', ['as'=>'code-editor.delete-files']);
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

//Login com Google OAuth2
$routes->get('/auth/google-login', 'AuthController::googleLoginRedirect', ['as'=>'auth.google.login']);
$routes->get('/auth/google-callback', 'AuthController::googleCallback', ['as'=>'auth.google.callback']);
$routes->post('auth/google-login', 'AuthController::googleLogin'); // Legacy endpoint

// Validações Custom Functions
$routes->post('/validation/deploy-custom', 'ValidationController::deployCustom', ['as'=>'validation.deployCustom']);
$routes->get('/validation/list-custom', 'ValidationController::listCustom', ['as'=>'validation.listCustom']);
$routes->post('/validation/deactivate-custom/(:num)', 'ValidationController::deactivateCustom/$1', ['as'=>'validation.deactivateCustom']);
$routes->delete('/validation/delete-custom/(:num)', 'ValidationController::deleteCustom/$1', ['as'=>'validation.deleteCustom']);

//Misc.
$routes->get('sitemap.xml', 'SitemapController::index');

// ========== ADMIN PANEL - COURSE MANAGEMENT (Admin Only) ==========
$routes->group('admin', ['filter' => 'adminauth'], function($routes) {
    
    // Dashboard
    $routes->get('dashboard', 'DashboardController::admin', ['as' => 'admin.dashboard']);
    $routes->get('downloadReturningStudentsCsv', 'DashboardController::downloadReturningStudentsCsv', ['as' => 'admin.downloadReturningStudentsCsv']);
    $routes->get('downloadStudentProgressCsv', 'DashboardController::downloadStudentProgressCsv', ['as' => 'admin.downloadStudentProgressCsv']);
    $routes->get('downloadFeedbackUsersCsv', 'DashboardController::downloadFeedbackUsersCsv', ['as' => 'admin.downloadFeedbackUsersCsv']);
    
    // Courses
    $routes->get('courses', 'ProgressAdminController::indexCourses', ['as' => 'admin.courses.index']);
    $routes->post('courses/add', 'ProgressAdminController::addCourse', ['as' => 'admin.courses.add']);
    $routes->post('courses/insert', 'ProgressAdminController::insertCourse', ['as' => 'admin.courses.insert']);
    $routes->post('courses/edit', 'ProgressAdminController::editCourse', ['as' => 'admin.courses.edit']);
    $routes->post('courses/update', 'ProgressAdminController::updateCourse', ['as' => 'admin.courses.update']);
    $routes->delete('courses/delete/(:num)', 'ProgressAdminController::deleteCourse/$1', ['as' => 'admin.courses.delete']);
    
    // Modules
    $routes->get('modules', 'ProgressAdminController::indexModules', ['as' => 'admin.modules.index']);
    $routes->get('modules/course/(:num)', 'ProgressAdminController::indexModules/$1', ['as' => 'admin.modules.by-course']);
    $routes->post('modules/add', 'ProgressAdminController::addModule', ['as' => 'admin.modules.add']);
    $routes->post('modules/add/(:num)', 'ProgressAdminController::addModule/$1', ['as' => 'admin.modules.add-with-course']);
    $routes->post('modules/insert', 'ProgressAdminController::insertModule', ['as' => 'admin.modules.insert']);
    $routes->post('modules/edit', 'ProgressAdminController::editModule', ['as' => 'admin.modules.edit']);
    $routes->post('modules/update', 'ProgressAdminController::updateModule', ['as' => 'admin.modules.update']);
    $routes->delete('modules/delete/(:num)', 'ProgressAdminController::deleteModule/$1', ['as' => 'admin.modules.delete']);
    
    // Videos
    $routes->get('videos', 'ProgressAdminController::indexVideos', ['as' => 'admin.videos.index']);
    $routes->get('videos/module/(:num)', 'ProgressAdminController::indexVideos/$1', ['as' => 'admin.videos.by-module']);
    $routes->post('videos/add', 'ProgressAdminController::addVideo', ['as' => 'admin.videos.add']);
    $routes->post('videos/add/(:num)', 'ProgressAdminController::addVideo/$1', ['as' => 'admin.videos.add-with-module']);
    $routes->post('videos/insert', 'ProgressAdminController::insertVideo', ['as' => 'admin.videos.insert']);
    $routes->post('videos/edit', 'ProgressAdminController::editVideo', ['as' => 'admin.videos.edit']);
    $routes->post('videos/update', 'ProgressAdminController::updateVideo', ['as' => 'admin.videos.update']);
    $routes->delete('videos/delete/(:num)', 'ProgressAdminController::deleteVideo/$1', ['as' => 'admin.videos.delete']);
    
    // UCs/Tasks
    $routes->get('ucs', 'ProgressAdminController::indexUCs', ['as' => 'admin.ucs.index']);
    $routes->get('ucs/video/(:num)', 'ProgressAdminController::indexUCs/$1', ['as' => 'admin.ucs.by-video']);
    $routes->post('ucs/add', 'ProgressAdminController::addUC', ['as' => 'admin.ucs.add']);
    $routes->post('ucs/add/(:num)', 'ProgressAdminController::addUC/$1', ['as' => 'admin.ucs.add-with-video']);
    $routes->post('ucs/insert', 'ProgressAdminController::insertUC', ['as' => 'admin.ucs.insert']);
    $routes->post('ucs/edit', 'ProgressAdminController::editUC', ['as' => 'admin.ucs.edit']);
    $routes->post('ucs/update', 'ProgressAdminController::updateUC', ['as' => 'admin.ucs.update']);
    $routes->delete('ucs/delete/(:num)', 'ProgressAdminController::deleteUC/$1', ['as' => 'admin.ucs.delete']);
});

$routes->get('admin/pagamento-inicial', 'PagamentoInicialAdminController::index');
$routes->post('admin/pagamento-inicial/autorizar/(:num)', 'PagamentoInicialAdminController::autorizar/$1');
$routes->get('subscription/initial-payment', 'SubscriptionController::initialPayment');

// --- ROTAS DO NOVO MÓDULO (Contratos e OS) ---
$routes->get('listTipoDocumento', 'TipoDocumentoController::index');
$routes->post('addTipoDocumento', 'TipoDocumentoController::add');
$routes->get('addTipoDocumento', 'TipoDocumentoController::add');
$routes->post('updTipoDocumento', 'TipoDocumentoController::upd');
$routes->post('insertTipoDocumento', 'TipoDocumentoController::insert');
$routes->post('updateTipoDocumento', 'TipoDocumentoController::update');
$routes->delete('deleteTipoDocumento/(:num)', 'TipoDocumentoController::delete/$1');
$routes->get('listStatus', 'StatusController::index');
$routes->post('addStatus', 'StatusController::add');
$routes->get('addStatus', 'StatusController::add');
$routes->post('updStatus', 'StatusController::upd');
$routes->post('insertStatus', 'StatusController::insert');
$routes->post('updateStatus', 'StatusController::update');
$routes->delete('deleteStatus/(:num)', 'StatusController::delete/$1');
$routes->get('listStatusRecebimento', 'StatusRecebimentoController::index');
$routes->post('addStatusRecebimento', 'StatusRecebimentoController::add');
$routes->get('addStatusRecebimento', 'StatusRecebimentoController::add');
$routes->post('updStatusRecebimento', 'StatusRecebimentoController::upd');
$routes->post('insertStatusRecebimento', 'StatusRecebimentoController::insert');
$routes->post('updateStatusRecebimento', 'StatusRecebimentoController::update');
$routes->delete('deleteStatusRecebimento/(:num)', 'StatusRecebimentoController::delete/$1');
$routes->get('listOrdemServico', 'OrdemServicoController::index');
$routes->post('addOrdemServico', 'OrdemServicoController::add');
$routes->get('addOrdemServico', 'OrdemServicoController::add');
$routes->post('updOrdemServico', 'OrdemServicoController::upd');
$routes->post('insertOrdemServico', 'OrdemServicoController::insert');
$routes->post('updateOrdemServico', 'OrdemServicoController::update');
$routes->post('concluirOrdemServico/(:num)', 'OrdemServicoController::concluir/$1');
$routes->delete('deleteOrdemServico/(:num)', 'OrdemServicoController::delete/$1');
$routes->get('listItemOs', 'ItemOsController::index');
$routes->post('addItemOs', 'ItemOsController::add');
$routes->get('addItemOs', 'ItemOsController::add');
$routes->post('updItemOs', 'ItemOsController::upd');
$routes->post('insertItemOs', 'ItemOsController::insert');
$routes->post('updateItemOs', 'ItemOsController::update');
$routes->delete('deleteItemOs/(:num)', 'ItemOsController::delete/$1');
$routes->get('listServico', 'ServicoController::index');
$routes->post('addServico', 'ServicoController::add');
$routes->get('addServico', 'ServicoController::add');
$routes->post('updServico', 'ServicoController::upd');
$routes->post('insertServico', 'ServicoController::insert');
$routes->post('updateServico', 'ServicoController::update');
$routes->delete('deleteServico/(:num)', 'ServicoController::delete/$1');
$routes->get('getServicoByMacro/(:num)', 'ServicoController::getByMacro/$1');
$routes->get('getMacroByServico/(:num)', 'ServicoController::getMacroByServico/$1');
$routes->get('listAtividadeMacro', 'AtividadeMacroController::index');
$routes->post('addAtividadeMacro', 'AtividadeMacroController::add');
$routes->get('addAtividadeMacro', 'AtividadeMacroController::add');
$routes->post('updAtividadeMacro', 'AtividadeMacroController::upd');
$routes->post('insertAtividadeMacro', 'AtividadeMacroController::insert');
$routes->post('updateAtividadeMacro', 'AtividadeMacroController::update');
$routes->delete('deleteAtividadeMacro/(:num)', 'AtividadeMacroController::delete/$1');
$routes->get('listAreaAtuacao', 'AreaAtuacaoController::index');
$routes->post('addAreaAtuacao', 'AreaAtuacaoController::add');
$routes->get('addAreaAtuacao', 'AreaAtuacaoController::add');
$routes->post('updAreaAtuacao', 'AreaAtuacaoController::upd');
$routes->post('insertAreaAtuacao', 'AreaAtuacaoController::insert');
$routes->post('updateAreaAtuacao', 'AreaAtuacaoController::update');
$routes->delete('deleteAreaAtuacao/(:num)', 'AreaAtuacaoController::delete/$1');
$routes->get('listCatalogoServicos', 'CatalogoServicosController::index');
$routes->post('addCatalogoServicos', 'CatalogoServicosController::add');
$routes->get('addCatalogoServicos', 'CatalogoServicosController::add');
$routes->post('updCatalogoServicos', 'CatalogoServicosController::upd');
$routes->post('insertCatalogoServicos', 'CatalogoServicosController::insert');
$routes->post('updateCatalogoServicos', 'CatalogoServicosController::update');
$routes->delete('deleteCatalogoServicos/(:num)', 'CatalogoServicosController::delete/$1');
$routes->get('listContrato', 'ContratoController::index');
$routes->post('addContrato', 'ContratoController::add');
$routes->get('addContrato', 'ContratoController::add');
$routes->post('updContrato', 'ContratoController::upd');
$routes->post('insertContrato', 'ContratoController::insert');
$routes->post('updateContrato', 'ContratoController::update');
$routes->delete('deleteContrato/(:num)', 'ContratoController::delete/$1');

$routes->get('listItemContrato', 'ItemContratoController::index');
$routes->post('addItemContrato', 'ItemContratoController::add');
$routes->get('addItemContrato', 'ItemContratoController::add');
$routes->post('updItemContrato', 'ItemContratoController::upd');
$routes->post('insertItemContrato', 'ItemContratoController::insert');
$routes->post('updateItemContrato', 'ItemContratoController::update');
$routes->delete('deleteItemContrato/(:num)', 'ItemContratoController::delete/$1');
$routes->get('listDocumentoRecebimento', 'DocumentoRecebimentoController::index');
$routes->post('addDocumentoRecebimento', 'DocumentoRecebimentoController::add');
$routes->get('addDocumentoRecebimento', 'DocumentoRecebimentoController::add');
$routes->post('updDocumentoRecebimento', 'DocumentoRecebimentoController::upd');
$routes->post('insertDocumentoRecebimento', 'DocumentoRecebimentoController::insert');
$routes->post('updateDocumentoRecebimento', 'DocumentoRecebimentoController::update');
$routes->delete('deleteDocumentoRecebimento/(:num)', 'DocumentoRecebimentoController::delete/$1');

$routes->get('listListaVerificacao', 'ListaVerificacaoController::index');
$routes->post('addListaVerificacao', 'ListaVerificacaoController::add');
$routes->get('addListaVerificacao', 'ListaVerificacaoController::add');
$routes->post('updListaVerificacao', 'ListaVerificacaoController::upd');
$routes->post('insertListaVerificacao', 'ListaVerificacaoController::insert');
$routes->post('updateListaVerificacao', 'ListaVerificacaoController::update');
$routes->delete('deleteListaVerificacao/(:num)', 'ListaVerificacaoController::delete/$1');
$routes->get('listAvaliacaoQualidadeSla', 'AvaliacaoQualidadeSlaController::index');
$routes->post('addAvaliacaoQualidadeSla', 'AvaliacaoQualidadeSlaController::add');
$routes->get('addAvaliacaoQualidadeSla', 'AvaliacaoQualidadeSlaController::add');
$routes->post('updAvaliacaoQualidadeSla', 'AvaliacaoQualidadeSlaController::upd');
$routes->post('insertAvaliacaoQualidadeSla', 'AvaliacaoQualidadeSlaController::insert');
$routes->post('updateAvaliacaoQualidadeSla', 'AvaliacaoQualidadeSlaController::update');
$routes->delete('deleteAvaliacaoQualidadeSla/(:num)', 'AvaliacaoQualidadeSlaController::delete/$1');

$routes->get('listReajusteItemContrato', 'ReajusteItemContratoController::index');
$routes->post('addReajusteItemContrato', 'ReajusteItemContratoController::add');
$routes->get('addReajusteItemContrato', 'ReajusteItemContratoController::add');
$routes->post('updReajusteItemContrato', 'ReajusteItemContratoController::upd');
$routes->post('insertReajusteItemContrato', 'ReajusteItemContratoController::insert');
$routes->post('updateReajusteItemContrato', 'ReajusteItemContratoController::update');
$routes->delete('deleteReajusteItemContrato/(:num)', 'ReajusteItemContratoController::delete/$1');

$routes->get('listMetrica', 'MetricaController::index');
$routes->post('addMetrica', 'MetricaController::add');
$routes->get('addMetrica', 'MetricaController::add');
$routes->post('updMetrica', 'MetricaController::upd');
$routes->post('insertMetrica', 'MetricaController::insert');
$routes->post('updateMetrica', 'MetricaController::update');
$routes->delete('deleteMetrica/(:num)', 'MetricaController::delete/$1');

$routes->get('api/areas/(:num)', 'ApiController::getAreasByCatalogo/$1');
$routes->get('api/atividades/(:num)', 'ApiController::getAtividadesByArea/$1');
$routes->get('api/servicos/(:num)', 'ApiController::getServicosByAtividade/$1');

$routes->get('api/itens_os/(:num)', 'ApiController::getItensByOs/$1');

$routes->get('api/os_details/(:num)', 'ApiController::getOsDetails/$1');
$routes->get('api/demandas_os/(:num)', 'ApiController::getDemandasByOs/$1');

// --- ROTAS DO MÓDULO ÁGIL (Sem RBAC) ---
$routes->group('agile', function($routes) {
    $routes->get('dashboard', 'AgileController::dashboard', ['as' => 'agile.dashboard']);
    $routes->get('demandas', 'AgileController::index', ['as' => 'agile.demandas']);
    $routes->get('demanda/add', 'AgileController::add', ['as' => 'agile.demanda.add']);
    $routes->post('demanda/insert', 'AgileController::insert', ['as' => 'agile.demanda.insert']);
    $routes->post('demanda/upd', 'AgileController::upd', ['as' => 'agile.demanda.upd']);
    $routes->post('demanda/update', 'AgileController::update', ['as' => 'agile.demanda.update']);
    $routes->delete('demanda/delete/(:num)', 'AgileController::delete/$1', ['as' => 'agile.demanda.delete']);
    
    // Cadastro de Sistemas
    $routes->get('sistemas', 'AgileController::sistemas', ['as' => 'agile.sistemas']);
    $routes->post('sistemas/salvar', 'AgileController::salvarSistema', ['as' => 'agile.sistemas.salvar']);
    $routes->delete('sistemas/deletar/(:num)', 'AgileController::deletarSistema/$1', ['as' => 'agile.sistemas.deletar']);
    
    // Backlog do Produto
    $routes->get('backlog/(:num)', 'AgileController::backlog/$1', ['as' => 'agile.backlog']);
    $routes->post('backlog/salvar-item', 'AgileController::salvarBacklogItem', ['as' => 'agile.backlog.salvar_item']);
    $routes->post('backlog/salvar-ordem', 'AgileController::salvarBacklogOrdem', ['as' => 'agile.backlog.salvar_ordem']);
    $routes->delete('backlog/deletar-item/(:num)', 'AgileController::deletarBacklogItem/$1', ['as' => 'agile.backlog.deletar_item']);
    
    // Kanban e Sprints
    $routes->get('kanban/(:num)', 'AgileController::kanban/$1', ['as' => 'agile.kanban']);
    $routes->post('kanban/update-status', 'AgileController::updateKanbanStatus', ['as' => 'agile.kanban.update_status']);
    $routes->post('sprint/salvar', 'AgileController::salvarSprint', ['as' => 'agile.sprint.salvar']);
    $routes->post('sprint/review', 'AgileController::salvarSprintReview', ['as' => 'agile.sprint.review']);
    
    // Cerimônias e Ritos
    $routes->post('cerimonia/agendar', 'AgileController::agendarCerimonia', ['as' => 'agile.cerimonia.agendar']);
    $routes->post('cerimonia/salvar', 'AgileController::salvarCerimonia', ['as' => 'agile.cerimonia.salvar']);
    $routes->delete('cerimonia/deletar/(:num)', 'AgileController::deletarCerimonia/$1', ['as' => 'agile.cerimonia.deletar']);
    
    // Homologação e Releases
    $routes->post('demanda/homologar', 'AgileController::salvarHomologacao', ['as' => 'agile.demanda.homologar']);
    $routes->post('demanda/release', 'AgileController::salvarRelease', ['as' => 'agile.demanda.release']);
});

