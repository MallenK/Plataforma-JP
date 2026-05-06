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
//  admin      → gestión completa (alumnos, entrenadores, config, bonos)
//  coach      → sus grupos, alumnos asignados, organizador, clases
//  alumno     → su propio perfil y documentación
//  staff      → documentación
//
//  NOTA: La funcionalidad de torneos está desactivada temporalmente.
//  Las rutas se conservan comentadas para reactivarlas en el futuro.
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

$routes->get('/alumnos', 'AlumnosController::index', [
    'filter' => ['auth', 'role:superadmin,admin,coach'],
]);

$routes->get('/alumnos/nuevo', 'AlumnosController::create', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);
$routes->post('/alumnos/nuevo', 'AlumnosController::store', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);

$routes->get('/alumnos/(:num)', 'AlumnosController::show/$1', [
    'filter' => ['auth', 'role:superadmin,admin,coach'],
]);

$routes->get('/alumnos/(:num)/editar', 'AlumnosController::edit/$1', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);
$routes->post('/alumnos/(:num)/editar', 'AlumnosController::update/$1', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);

$routes->post('/alumnos/(:num)/eliminar', 'AlumnosController::destroy/$1', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);

// ── Anotaciones de alumno ──────────────────────────────────────────────
//  POST /alumnos/:id/anotaciones        → crear anotación — todos los roles
//  POST /anotaciones/:id/eliminar       → eliminar — autor, admin, superadmin
$routes->post('/alumnos/(:num)/anotaciones', 'AnnotationController::store/$1', [
    'filter' => 'auth',
]);
$routes->post('/anotaciones/(:num)/eliminar', 'AnnotationController::destroy/$1', [
    'filter' => 'auth',
]);

$routes->get('/alumno', 'AlumnosController::profile', [
    'filter' => ['auth', 'role:superadmin,admin,player'],
]);

$routes->post('/alumno/save', 'AlumnosController::saveProfile', [
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
// CLASES Y CALENDARIO
//
//  GET  /clases               → todos los roles autenticados (jugadores ven las suyas)
//  GET  /clases/:id           → todos los roles (el controller filtra por rol)
//  POST /clases/:id/confirmar → cualquier usuario asignado
//  Todo lo demás              → admin, superadmin, staff, coach
// ------------------------------------------------------------

// ── Listado / Calendario ────────────────────────────────────
$routes->get('clases', 'ClasesController::index', [
    'filter' => 'auth',
]);

// ── AJAX: datos calendario y opciones ──────────────────────
$routes->get('clases/api/calendario', 'ClasesController::calendario', [
    'filter' => 'auth',
]);
$routes->get('clases/api/opciones', 'ClasesController::opciones', [
    'filter' => ['auth', 'role:superadmin,admin,staff,coach'],
]);

// ── Crear ──────────────────────────────────────────────────
$routes->get('clases/nueva', 'ClasesController::create', [
    'filter' => ['auth', 'role:superadmin,admin,staff,coach'],
]);
$routes->post('clases/nueva', 'ClasesController::store', [
    'filter' => ['auth', 'role:superadmin,admin,staff,coach'],
]);

// ── Quick-create (AJAX, desde Dashboard / Torneos) ─────────
$routes->post('clases/rapida', 'ClasesController::quickCreate', [
    'filter' => ['auth', 'role:superadmin,admin,staff,coach'],
]);

// ── Detalle ────────────────────────────────────────────────
$routes->get('clases/(:num)', 'ClasesController::show/$1', [
    'filter' => 'auth',
]);

// ── Confirmar asistencia (jugador convocado) ───────────────
$routes->post('clases/(:num)/confirmar', 'ClasesController::respond/$1', [
    'filter' => 'auth',
]);

// ── Editar / Completar / Cancelar / Eliminar ───────────────
$routes->get('clases/(:num)/editar', 'ClasesController::edit/$1', [
    'filter' => ['auth', 'role:superadmin,admin,staff,coach'],
]);
$routes->post('clases/(:num)/editar', 'ClasesController::update/$1', [
    'filter' => ['auth', 'role:superadmin,admin,staff,coach'],
]);
$routes->post('clases/(:num)/completar', 'ClasesController::complete/$1', [
    'filter' => ['auth', 'role:superadmin,admin,staff,coach'],
]);
$routes->post('clases/(:num)/cancelar', 'ClasesController::cancel/$1', [
    'filter' => ['auth', 'role:superadmin,admin,staff,coach'],
]);
$routes->post('clases/(:num)/eliminar', 'ClasesController::destroy/$1', [
    'filter' => ['auth', 'role:superadmin,admin,staff,coach'],
]);

// ── Observaciones y asistencia ─────────────────────────────
$routes->post('clases/(:num)/observaciones', 'ClasesController::saveObservations/$1', [
    'filter' => ['auth', 'role:superadmin,admin,staff,coach'],
]);
$routes->post('clases/(:num)/asistencia', 'ClasesController::saveAttendance/$1', [
    'filter' => ['auth', 'role:superadmin,admin,staff,coach'],
]);

// ── Entrenadores ───────────────────────────────────────────
$routes->post('clases/(:num)/coaches/add', 'ClasesController::addCoach/$1', [
    'filter' => ['auth', 'role:superadmin,admin,staff,coach'],
]);
$routes->post('clases/(:num)/coaches/(:num)/remove', 'ClasesController::removeCoach/$1/$2', [
    'filter' => ['auth', 'role:superadmin,admin,staff,coach'],
]);

// ── Jugadores ──────────────────────────────────────────────
$routes->post('clases/(:num)/jugadores/add', 'ClasesController::addPlayer/$1', [
    'filter' => ['auth', 'role:superadmin,admin,staff,coach'],
]);
$routes->post('clases/(:num)/jugadores/(:num)/remove', 'ClasesController::removePlayer/$1/$2', [
    'filter' => ['auth', 'role:superadmin,admin,staff,coach'],
]);


// ------------------------------------------------------------
// BONOS
// Membresías y bonos — solo admin y superadmin.
// ------------------------------------------------------------

$routes->get('bonos', 'BonosController::index', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);

$routes->post('bonos/store', 'BonosController::store', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);

$routes->post('bonos/check-active', 'BonosController::checkActive', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);

$routes->get('bonos/(:num)', 'BonosController::show/$1', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);

$routes->post('bonos/(:num)/assign', 'BonosController::assign/$1', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);

$routes->post('bonos/(:num)/update', 'BonosController::update/$1', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);

$routes->post('bonos/(:num)/delete', 'BonosController::destroy/$1', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);


// ------------------------------------------------------------
// TORNEOS Y CAMPUS — DESACTIVADO TEMPORALMENTE
//
// Funcionalidad conservada para reactivación futura.
// Para reactivar: descomentar el bloque completo.
// ------------------------------------------------------------

// // ── Listado ────────────────────────────────────────────────
// $routes->get('torneos', 'TorneosController::index', [
//     'filter' => 'auth',
// ]);
// // ── Crear ──────────────────────────────────────────────────
// $routes->get('torneos/nuevo', 'TorneosController::create', [
//     'filter' => ['auth', 'role:superadmin,admin'],
// ]);
// $routes->post('torneos/nuevo', 'TorneosController::store', [
//     'filter' => ['auth', 'role:superadmin,admin'],
// ]);
// // ── Participantes externos ─────────────────────────────────
// $routes->post('torneos/externos/create', 'TorneosController::createExternal', [
//     'filter' => ['auth', 'role:superadmin,admin'],
// ]);
// // ── Detalle ────────────────────────────────────────────────
// $routes->get('torneos/(:num)', 'TorneosController::show/$1', [
//     'filter' => 'auth',
// ]);
// // ── Confirmación de asistencia (cualquier usuario convocado)
// $routes->post('torneos/(:num)/respond', 'TorneosController::respond/$1', [
//     'filter' => 'auth',
// ]);
// // ── Editar / Cancelar / Eliminar (solo admin) ──────────────
// $routes->get('torneos/(:num)/editar', 'TorneosController::edit/$1', [
//     'filter' => ['auth', 'role:superadmin,admin'],
// ]);
// $routes->post('torneos/(:num)/editar', 'TorneosController::update/$1', [
//     'filter' => ['auth', 'role:superadmin,admin'],
// ]);
// $routes->post('torneos/(:num)/cancelar', 'TorneosController::cancel/$1', [
//     'filter' => ['auth', 'role:superadmin,admin'],
// ]);
// $routes->post('torneos/(:num)/eliminar', 'TorneosController::destroy/$1', [
//     'filter' => ['auth', 'role:superadmin,admin'],
// ]);
// // ── Equipos ────────────────────────────────────────────────
// $routes->post('torneos/(:num)/equipos/create', 'TorneosController::createTeam/$1', [
//     'filter' => ['auth', 'role:superadmin,admin'],
// ]);
// $routes->post('torneos/(:num)/equipos/(:num)/delete', 'TorneosController::deleteTeam/$1/$2', [
//     'filter' => ['auth', 'role:superadmin,admin'],
// ]);
// // ── Miembros ───────────────────────────────────────────────
// $routes->post('torneos/(:num)/equipos/(:num)/miembros/add', 'TorneosController::addMember/$1/$2', [
//     'filter' => ['auth', 'role:superadmin,admin'],
// ]);
// $routes->post('torneos/(:num)/miembros/(:num)/remove', 'TorneosController::removeMember/$1/$2', [
//     'filter' => ['auth', 'role:superadmin,admin'],
// ]);
// // ── Notificaciones ─────────────────────────────────────────
// $routes->post('torneos/(:num)/notificar', 'TorneosController::sendNotifications/$1', [
//     'filter' => ['auth', 'role:superadmin,admin'],
// ]);
// // ── Resultados ─────────────────────────────────────────────
// $routes->post('torneos/(:num)/resultado', 'TorneosController::saveResult/$1', [
//     'filter' => ['auth', 'role:superadmin,admin'],
// ]);


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
$routes->post('configuracion/staff/(:num)/delete', 'ConfiguracionController::deleteStaff/$1', [
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

$routes->post('perfil/update', 'PerfilController::update', [
    'filter' => 'auth',
]);

$routes->post('perfil/(:num)/update', 'PerfilController::update/$1', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);

$routes->post('perfil/(:num)/reset-password', 'PerfilController::resetPassword/$1', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);


// ------------------------------------------------------------
// NOTIFICACIONES
//
//  GET  /notificaciones         → centro de notificaciones — todos los roles
//  POST /notificaciones/send    → enviar notificación (individual: todos; grupal: admin,superadmin,coach)
//  POST /notificaciones/:id/read  → marcar una notificación como leída
//  POST /notificaciones/read-all  → marcar todas como leídas
//  GET  /notificaciones/:id/download → descargar adjunto
//  GET  /notificaciones/latest  → AJAX campana navbar (últimas N + unread count)
// ------------------------------------------------------------

$routes->get('notificaciones', 'NotificacionesController::index', [
    'filter' => 'auth',
]);

$routes->post('notificaciones/send', 'NotificacionesController::send', [
    'filter' => 'auth',
]);

$routes->post('notificaciones/read-all', 'NotificacionesController::ajaxMarkAllRead', [
    'filter' => 'auth',
]);

$routes->post('notificaciones/(:num)/read', 'NotificacionesController::ajaxMarkRead/$1', [
    'filter' => 'auth',
]);

$routes->get('notificaciones/(:num)/download', 'NotificacionesController::download/$1', [
    'filter' => 'auth',
]);

$routes->get('notificaciones/latest', 'NotificacionesController::ajaxLatest', [
    'filter' => 'auth',
]);


// ------------------------------------------------------------
// MENSAJES (CHAT)
//
//  GET  /mensajes                     → bandeja de entrada
//  POST /mensajes/open                → abrir/crear conversación
//  POST /mensajes/send                → enviar mensaje
//  GET  /mensajes/:id/poll            → polling mensajes nuevos (AJAX)
//  GET  /mensajes/conversations       → lista conversaciones (AJAX)
//  GET  /mensajes/download/:id        → descargar archivo de mensaje
// ------------------------------------------------------------

$routes->get('mensajes', 'MensajesController::index', [
    'filter' => 'auth',
]);

$routes->post('mensajes/open', 'MensajesController::ajaxOpenConversation', [
    'filter' => 'auth',
]);

$routes->post('mensajes/send', 'MensajesController::ajaxSend', [
    'filter' => 'auth',
]);

$routes->get('mensajes/(:num)/poll', 'MensajesController::ajaxPoll/$1', [
    'filter' => 'auth',
]);

$routes->get('mensajes/conversations', 'MensajesController::ajaxConversations', [
    'filter' => 'auth',
]);

$routes->get('mensajes/download/(:num)', 'MensajesController::download/$1', [
    'filter' => 'auth',
]);


// ------------------------------------------------------------
// COMPRAS (LISTA DE COMPRAS)
//
//  GET  /compras              → listado general — todos los no-jugadores
//  POST /compras/store        → crear solicitud — todos los no-jugadores
//  POST /compras/:id/estado   → cambiar estado + comentario — admin, superadmin
//  POST /compras/:id/eliminar → eliminar solicitud — admin, superadmin
// ------------------------------------------------------------

$routes->get('compras', 'ComprasController::index', [
    'filter' => ['auth', 'role:superadmin,admin,coach,staff'],
]);

$routes->post('compras/store', 'ComprasController::store', [
    'filter' => ['auth', 'role:superadmin,admin,coach,staff'],
]);

$routes->post('compras/(:num)/estado', 'ComprasController::updateStatus/$1', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);

$routes->post('compras/(:num)/eliminar', 'ComprasController::destroy/$1', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);


// ------------------------------------------------------------
// AVATARES
// Cualquier usuario autenticado puede gestionar su propio avatar.
// Con /:id solo admin/superadmin pueden gestionar el de otro usuario.
// ------------------------------------------------------------

$routes->post('avatar/upload', 'AvatarController::upload', [
    'filter' => 'auth',
]);
$routes->post('avatar/upload/(:num)', 'AvatarController::upload/$1', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);
$routes->post('avatar/delete', 'AvatarController::delete', [
    'filter' => 'auth',
]);
$routes->post('avatar/delete/(:num)', 'AvatarController::delete/$1', [
    'filter' => ['auth', 'role:superadmin,admin'],
]);
