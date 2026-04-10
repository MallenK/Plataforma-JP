<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// ============================================================
// MATRIZ DE PERMISOS POR ROL
// ============================================================
//
//  superadmin → acceso total a todo
//  admin      → gestión completa (alumnos, entrenadores, config, finanzas, bonos)
//  coach      → sus grupos, alumnos asignados, organizador, clases, torneos
//  alumno     → su propio perfil y documentación
//  staff      → torneos y documentación
//
//  Cada ruta protegida lleva DOS filtros encadenados:
//    1. 'auth'  → verifica que hay sesión activa (redirige a /login si no)
//    2. 'role:X,Y' → verifica que el rol está permitido (devuelve 403 si no)
// ============================================================


// ------------------------------------------------------------
// RUTAS PÚBLICAS — sin sesión requerida
// ------------------------------------------------------------

$routes->get('/', function () {
    return redirect()->to('/login');
});

$routes->get('login',  'AuthController::login');
$routes->post('login', 'AuthController::loginPost');

$routes->get('register',  'AuthController::register');
$routes->post('register', 'AuthController::registerPost');

$routes->get('/forgot-password',  'AuthController::forgotPassword');
$routes->post('/forgot-password', 'AuthController::forgotPasswordPost');

$routes->get('/reset-password',  'AuthController::resetPassword');
$routes->post('/reset-password', 'AuthController::resetPasswordPost');

$routes->get('logout', 'AuthController::logout');


// ------------------------------------------------------------
// DASHBOARD
// Todos los roles autenticados acceden, el contenido varía por rol.
// ------------------------------------------------------------

$routes->get('dashboard', 'DashboardController::index', [
    'filter' => 'auth',
]);

// Stats AJAX — solo admin y superadmin
$routes->post('dashboard/stats', 'DashboardController::getStats', [
    'filter' => ['auth', 'role:superadmin,admin'],
    'as'     => 'dashboard_stats',
]);


// ------------------------------------------------------------
// ALUMNOS
//
//  /alumnos              → listado global — admin, superadmin, coach
//  /alumnos/nuevo        → formulario nuevo alumno — admin, superadmin
//  /alumnos/:id          → perfil completo de un alumno — admin, superadmin, coach
//  /alumnos/:id/editar   → formulario edición — admin, superadmin
//  /alumnos/:id/eliminar → baja lógica (POST) — admin, superadmin
//  /alumno               → perfil propio — alumno + admin/superadmin para editar
//  /alumno/save          → guardar perfil — alumno + admin/superadmin
// ------------------------------------------------------------

$routes->get('/alumnos', 'PlayerController::index', [
    'filter' => ['auth', 'role:superadmin,admin,coach'],
]);

$routes->get('/alumnos/nuevo', 'PlayerController::create', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);
$routes->post('/alumnos/nuevo', 'PlayerController::store', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);

$routes->get('/alumnos/(:num)', 'PlayerController::show/$1', [
    'filter' => ['auth', 'role:superadmin,admin,coach'],
]);

$routes->get('/alumnos/(:num)/editar', 'PlayerController::edit/$1', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);
$routes->post('/alumnos/(:num)/editar', 'PlayerController::update/$1', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);

$routes->post('/alumnos/(:num)/eliminar', 'PlayerController::destroy/$1', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);

$routes->get('/alumno', 'PlayerController::profile', [
    'filter' => ['auth', 'role:superadmin,admin,player'],
]);

$routes->post('/alumno/save', 'PlayerController::saveProfile', [
    'filter' => ['auth', 'role:superadmin,admin,player'],
]);


// ------------------------------------------------------------
// ENTRENADORES
//
//  /entrenadores              → listado — admin, superadmin
//  /entrenadores/nuevo        → formulario nuevo entrenador — admin, superadmin
//  /entrenadores/:id          → perfil completo — admin, superadmin
//  /entrenadores/:id/editar   → formulario edición — admin, superadmin
//  /entrenadores/:id/eliminar → baja lógica (POST) — admin, superadmin
// ------------------------------------------------------------

$routes->get('entrenadores', 'EntrenadoresController::index', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);

$routes->get('entrenadores/nuevo', 'EntrenadoresController::create', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);
$routes->post('entrenadores/nuevo', 'EntrenadoresController::store', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);

$routes->get('entrenadores/(:num)', 'EntrenadoresController::show/$1', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);

$routes->get('entrenadores/(:num)/editar', 'EntrenadoresController::edit/$1', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);
$routes->post('entrenadores/(:num)/editar', 'EntrenadoresController::update/$1', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);

$routes->post('entrenadores/(:num)/eliminar', 'EntrenadoresController::destroy/$1', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);


// ------------------------------------------------------------
// ORGANIZADOR (Calendario)
// Planificación de clases — admin, superadmin, coach.
// ------------------------------------------------------------

$routes->get('organizador', 'OrganizadorController::index', [
    'filter' => ['auth', 'role:superadmin,admin,coach'],
]);


// ------------------------------------------------------------
// CLASES
// Gestión de sesiones de entrenamiento — admin, superadmin, coach.
// ------------------------------------------------------------

$routes->get('clases', 'ClasesController::index', [
    'filter' => ['auth', 'role:superadmin,admin,coach'],
]);


// ------------------------------------------------------------
// BONOS
// Membresías y bonos — solo admin y superadmin.
// ------------------------------------------------------------

$routes->get('bonos', 'BonosController::index', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);


// ------------------------------------------------------------
// TORNEOS
// Visibles para todos excepto alumno (solo participan, no gestionan).
// ------------------------------------------------------------

$routes->get('torneos', 'TorneosController::index', [
    'filter' => ['auth', 'role:superadmin,admin,coach,staff'],
]);


// ------------------------------------------------------------
// DOCUMENTACIÓN
// Accesible para todos los roles — es contenido formativo.
// ------------------------------------------------------------

$routes->get('documentacion', 'DocumentacionController::index', [
    'filter' => 'auth',
]);

$routes->get('documentacion/(:num)', 'DocumentacionController::index/$1', [
    'filter' => 'auth',
]);

// ── Archivos: descarga y previsualización ──────────────────────────
// Todos los roles autenticados (el controller valida permisos por carpeta)
$routes->get('documentacion/file/(:num)/download', 'DocumentacionController::download/$1', [
    'filter' => 'auth',
]);
$routes->get('documentacion/file/(:num)/preview', 'DocumentacionController::preview/$1', [
    'filter' => 'auth',
]);
$routes->post('documentacion/file/(:num)/delete', 'DocumentacionController::deleteFile/$1', [
    'filter' => 'auth',
]);

// ── Subida ─────────────────────────────────────────────────────────
$routes->post('documentacion/upload', 'DocumentacionController::upload', [
    'filter' => 'auth',
]);

// ── Gestión de carpetas (solo admin/superadmin) ────────────────────
$routes->post('documentacion/folder/create', 'DocumentacionController::createFolder', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);
$routes->post('documentacion/folder/(:num)/delete', 'DocumentacionController::deleteFolder/$1', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);

// ── Permisos de carpetas internas (solo admin/superadmin) ──────────
$routes->post('documentacion/folder/(:num)/permissions', 'DocumentacionController::savePermissions/$1', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);


// ------------------------------------------------------------
// FINANZAS
// Control económico — solo admin y superadmin.
// ------------------------------------------------------------

$routes->get('finanzas', 'FinanzasController::index', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);


// ------------------------------------------------------------
// CONFIGURACIÓN
//
//  GET  /configuracion          → todos los roles (no-admin ve solo General, read-only)
//  POST /configuracion/*        → solo admin y superadmin
// ------------------------------------------------------------

$routes->get('configuracion', 'ConfiguracionController::index', [
    'filter' => 'auth',
]);

$routes->get('configuracion/(:num)', 'ConfiguracionController::index/$1', [
    'filter' => 'auth',
]);

// ── General ───────────────────────────────────────────────
$routes->post('configuracion/general/save', 'ConfiguracionController::saveGeneral', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);

// ── Gestión de Staff ───────────────────────────────────────
$routes->post('configuracion/staff/create', 'ConfiguracionController::createStaff', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);
$routes->post('configuracion/staff/(:num)/role', 'ConfiguracionController::updateStaffRole/$1', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);
$routes->post('configuracion/staff/(:num)/deactivate', 'ConfiguracionController::deactivateStaff/$1', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);
$routes->post('configuracion/staff/(:num)/activate', 'ConfiguracionController::activateStaff/$1', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);

// ── Campos y Sedes ─────────────────────────────────────────
$routes->post('configuracion/sedes/create', 'ConfiguracionController::createSede', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);
$routes->post('configuracion/sedes/(:num)/edit', 'ConfiguracionController::updateSede/$1', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);
$routes->post('configuracion/sedes/(:num)/delete', 'ConfiguracionController::deleteSede/$1', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);

// ── Facturación — Tipos de Bono ────────────────────────────
$routes->post('configuracion/bonos/create', 'ConfiguracionController::createBonoType', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);
$routes->post('configuracion/bonos/(:num)/edit', 'ConfiguracionController::updateBonoType/$1', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);
$routes->post('configuracion/bonos/(:num)/delete', 'ConfiguracionController::deleteBonoType/$1', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);

// ── Notificaciones ─────────────────────────────────────────
$routes->post('configuracion/notificaciones/smtp', 'ConfiguracionController::saveSmtp', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);
$routes->post('configuracion/notificaciones/toggles', 'ConfiguracionController::saveNotifToggles', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);
$routes->post('configuracion/notificaciones/send', 'ConfiguracionController::sendEmail', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);

// ── Seguridad ──────────────────────────────────────────────
$routes->post('configuracion/seguridad/save', 'ConfiguracionController::saveSeguridad', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);

// ── Web Pública ────────────────────────────────────────────
$routes->post('configuracion/web/save', 'ConfiguracionController::saveWeb', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);


// ------------------------------------------------------------
// PERFIL DE USUARIO
// Cada usuario ve su propio perfil — todos los roles.
// Con $id solo admin/superadmin (la lógica de acceso por ID
// está en PerfilController).
// ------------------------------------------------------------

$routes->get('perfil', 'PerfilController::index', [
    'filter' => 'auth',
]);

$routes->get('perfil/(:num)', 'PerfilController::index/$1', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);
