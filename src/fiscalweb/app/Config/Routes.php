<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index', ['as'=>'home']);
$routes->post('/', 'Home::index', ['as'=>'home']);

// Usuário CRUD - FiscalWeb
$routes->get('/usuario', 'UsuarioController::index', ['as'=>'listUsuario']);
$routes->get('/listUsuario', 'UsuarioController::index');

$routes->get('/addUsuario', 'UsuarioController::add', ['as'=>'addUsuario']);
$routes->post('/insertUsuario', 'UsuarioController::insert', ['as'=>'Usuario.insert']);

$routes->get('/updUsuario/(:num)', 'UsuarioController::upd/$1', ['as'=>'updUsuario']);
$routes->post('/updateUsuario/(:num)', 'UsuarioController::update/$1', ['as'=>'Usuario.update']);

$routes->post('/deleteUsuario/(:num)', 'UsuarioController::del/$1', ['as' => 'Usuario.delete']);
