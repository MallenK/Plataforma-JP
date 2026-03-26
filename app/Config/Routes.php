<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */


$routes->get('/', function() {
    return redirect()->to('/login');
});

$routes->get('login', 'AuthController::login');
$routes->post('login', 'AuthController::loginPost');

$routes->get('register', 'AuthController::register');
$routes->post('register', 'AuthController::registerPost');

$routes->get('/forgot-password', 'AuthController::forgotPassword');
$routes->post('/forgot-password', 'AuthController::forgotPasswordPost');

$routes->get('/reset-password', 'AuthController::resetPassword');
$routes->post('/reset-password', 'AuthController::resetPasswordPost');

$routes->get('logout', 'AuthController::logout');



$routes->get('dashboard', 'DashboardController::index', ['filter' => 'auth']);
$routes->post('dashboard/stats', 'DashboardController::getStats', ['filter' => 'auth','as' => 'dashboard_stats']);

$routes->get('alumnos', 'AlumnosController::index', ['filter' => 'auth']);
$routes->get('entrenadores', 'EntrenadoresController::index', ['filter' => 'auth']);
$routes->get('torneos', 'TorneosController::index', ['filter' => 'auth']);
$routes->get('documentacion', 'DocumentacionController::index', ['filter' => 'auth']);
$routes->get('configuracion', 'ConfiguracionController::index', ['filter' => 'auth']);



$routes->get('perfil', 'PerfilController::index', ['filter' => 'auth']);
$routes->get('perfil/(:num)', 'PerfilController::index/$1', ['filter' => 'auth']);