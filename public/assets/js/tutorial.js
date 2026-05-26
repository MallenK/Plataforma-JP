/**
 * JP Preparation — Tutorial Interactivo
 * Motor de guía paso a paso por rol, con spotlight opcional sobre elementos.
 */
(function () {
    'use strict';

    // ─────────────────────────────────────────────────────────────────────────
    //  DATOS DEL TUTORIAL POR ROL
    // ─────────────────────────────────────────────────────────────────────────

    const STEPS = {

        player: [
            {
                title: '¡Bienvenido a JP Preparation! 👋',
                body: 'Esta es tu plataforma personal de entrenamiento. Te mostramos en unos pasos todo lo que puedes hacer aquí.',
                icon: 'bi-stars',
                color: '#f59e0b',
                target: null,
            },
            {
                title: 'Panel principal',
                body: 'Desde el <strong>Dashboard</strong> ves de un vistazo tus próximas sesiones, los bonos que te quedan y las últimas notificaciones.',
                icon: 'bi-speedometer2',
                color: '#3b82f6',
                target: 'a[href*="dashboard"]',
                page: '/dashboard',
            },
            {
                title: 'Tu ficha técnica',
                body: 'En <strong>Mi ficha</strong> encontrarás tus datos deportivos: categoría, equipo, liga, posición, altura, peso y notas médicas. Puedes editarlos en cualquier momento.',
                icon: 'bi-person-badge-fill',
                color: '#8b5cf6',
                target: 'a[href*="alumno"]',
                page: '/alumno',
            },
            {
                title: 'Tus sesiones de entrenamiento',
                body: 'En <strong>Clases</strong> verás el calendario con todas tus sesiones. Las sesiones programadas aparecen en azul, las completadas en verde y las canceladas en gris.',
                icon: 'bi-calendar3',
                color: '#06b6d4',
                target: 'a[href*="clases"]',
                page: '/clases',
            },
            {
                title: 'Avisar una ausencia',
                body: 'Si no puedes ir a una sesión, entra en su detalle y usa el botón <strong>"Notificar ausencia"</strong>. Puedes añadir un motivo. Si avisas después de las 10:00 del mismo día, la plataforma te lo indicará.',
                icon: 'bi-calendar-x-fill',
                color: '#ef4444',
                target: null,
                page: '/clases',
            },
            {
                title: 'Tus documentos',
                body: 'En <strong>Documentación</strong> tienes acceso a tu carpeta personal y a los documentos públicos de la academia: material formativo, planes de entrenamiento, etc.',
                icon: 'bi-folder-fill',
                color: '#f97316',
                target: 'a[href*="documentacion"]',
                page: '/documentacion',
            },
            {
                title: 'Mensajes directos',
                body: 'Usa <strong>Mensajes</strong> para chatear directamente con tu entrenador o el personal de la academia. Puedes enviar archivos adjuntos.',
                icon: 'bi-chat-dots-fill',
                color: '#10b981',
                target: 'a[href*="mensajes"]',
                page: '/mensajes',
            },
            {
                title: 'Notificaciones',
                body: 'La <strong>campana</strong> en la barra superior te avisa de novedades: nuevas sesiones, documentos, mensajes del entrenador, etc.',
                icon: 'bi-bell-fill',
                color: '#f59e0b',
                target: '#topbar-notif-btn',
            },
            {
                title: 'Tu perfil de cuenta',
                body: 'Desde <strong>Mi perfil</strong> puedes cambiar tu foto de avatar, actualizar tu email y cambiar tu contraseña.',
                icon: 'bi-person-circle',
                color: '#6366f1',
                target: 'a[href*="perfil"]',
                page: '/perfil',
            },
            {
                title: '¡Ya estás listo!',
                body: 'Si necesitas ver este tutorial de nuevo, haz clic en el botón <strong>?</strong> de la barra superior. ¡Mucho éxito en tus entrenamientos!',
                icon: 'bi-check-circle-fill',
                color: '#10b981',
                target: null,
            },
        ],

        coach: [
            {
                title: 'Bienvenido, entrenador 🏆',
                body: 'Desde esta plataforma gestionas tus grupos, registras asistencia, añades observaciones y te comunicas con los alumnos.',
                icon: 'bi-trophy-fill',
                color: '#f59e0b',
                target: null,
            },
            {
                title: 'Dashboard',
                body: 'El panel te muestra un resumen de sesiones esta semana, alumnos asignados y las próximas clases programadas.',
                icon: 'bi-speedometer2',
                color: '#3b82f6',
                target: 'a[href*="dashboard"]',
                page: '/dashboard',
            },
            {
                title: 'Listado de alumnos',
                body: 'En <strong>Alumnos</strong> puedes ver todos los jugadores que tienes asignados. Filtra por nombre, categoría o estado.',
                icon: 'bi-people-fill',
                color: '#8b5cf6',
                target: 'a[href*="alumnos"]',
                page: '/alumnos',
            },
            {
                title: 'Ficha del alumno',
                body: 'Entra en un alumno para ver su ficha completa: datos deportivos, historial de sesiones, <strong>anotaciones</strong> (públicas e internas) y sus documentos personales.',
                icon: 'bi-person-lines-fill',
                color: '#06b6d4',
                target: null,
                page: '/alumnos',
            },
            {
                title: 'Anotaciones',
                body: 'Puedes añadir <strong>anotaciones públicas</strong> (el alumno las ve) o <strong>internas</strong> (solo el staff). También puedes adjuntar un archivo a cada anotación.',
                icon: 'bi-chat-square-text-fill',
                color: '#f97316',
                target: null,
            },
            {
                title: 'Gestión de clases',
                body: 'En <strong>Clases</strong> ves el calendario completo. Puedes crear una clase rápida directamente desde el calendario o crear una sesión completa con todos los detalles.',
                icon: 'bi-calendar3',
                color: '#3b82f6',
                target: 'a[href*="clases"]',
                page: '/clases',
            },
            {
                title: 'Detalle de sesión',
                body: 'Dentro de cada sesión puedes: ver los alumnos convocados, marcar <strong>presente / ausente</strong> a cada alumno, añadir el motivo de ausencia y escribir observaciones individuales por jugador.',
                icon: 'bi-clipboard2-check-fill',
                color: '#10b981',
                target: null,
            },
            {
                title: 'Completar una sesión',
                body: 'Al marcar la sesión como <strong>Completada</strong>, el sistema descuenta automáticamente un bono a los alumnos marcados como <em>presente</em>.',
                icon: 'bi-check2-circle',
                color: '#10b981',
                target: null,
            },
            {
                title: 'Documentación',
                body: 'Tienes acceso a los documentos públicos y a los materiales de las carpetas internas a las que tengas permiso.',
                icon: 'bi-folder-fill',
                color: '#f97316',
                target: 'a[href*="documentacion"]',
                page: '/documentacion',
            },
            {
                title: 'Mensajes y Notificaciones',
                body: 'Usa <strong>Mensajes</strong> para comunicarte individualmente con alumnos o compañeros. Desde <strong>Notificaciones</strong> puedes enviar avisos grupales o individuales.',
                icon: 'bi-chat-dots-fill',
                color: '#10b981',
                target: 'a[href*="mensajes"]',
            },
            {
                title: '¡Listo para entrenar!',
                body: 'Recuerda que puedes reabrir este tutorial desde el botón <strong>?</strong> en la barra superior.',
                icon: 'bi-check-circle-fill',
                color: '#10b981',
                target: null,
            },
        ],

        staff: [
            {
                title: 'Bienvenido al panel de Staff 📋',
                body: 'Como miembro del personal de apoyo, tienes acceso a la gestión administrativa de la academia.',
                icon: 'bi-briefcase-fill',
                color: '#6366f1',
                target: null,
            },
            {
                title: 'Dashboard',
                body: 'El panel te muestra un resumen de actividad reciente: sesiones del día, alumnos activos y documentos recientes.',
                icon: 'bi-speedometer2',
                color: '#3b82f6',
                target: 'a[href*="dashboard"]',
            },
            {
                title: 'Alumnos',
                body: 'Puedes consultar la ficha completa de cualquier alumno. Verás su información deportiva, historial de sesiones y observaciones.',
                icon: 'bi-people-fill',
                color: '#8b5cf6',
                target: 'a[href*="alumnos"]',
            },
            {
                title: 'Clases y calendario',
                body: 'Tienes acceso completo al calendario de sesiones. Puedes crear, editar y gestionar las sesiones de entrenamiento.',
                icon: 'bi-calendar3',
                color: '#3b82f6',
                target: 'a[href*="clases"]',
            },
            {
                title: 'Documentación',
                body: 'Gestiona las carpetas y archivos de la academia. Puedes subir nuevos documentos a las carpetas en las que tienes permiso de escritura.',
                icon: 'bi-folder-fill',
                color: '#f97316',
                target: 'a[href*="documentacion"]',
            },
            {
                title: 'Lista de compras',
                body: 'En <strong>Compras</strong> puedes añadir solicitudes de material: indica el nombre del artículo, categoría, precio estimado y prioridad. Los administradores aprobarán o denegarán cada solicitud.',
                icon: 'bi-cart-fill',
                color: '#ef4444',
                target: 'a[href*="compras"]',
            },
            {
                title: 'Mensajes y Notificaciones',
                body: 'Puedes enviar mensajes directos y notificaciones individuales a cualquier usuario de la plataforma.',
                icon: 'bi-chat-dots-fill',
                color: '#10b981',
                target: 'a[href*="mensajes"]',
            },
            {
                title: '¡Todo listo!',
                body: 'Recuerda que puedes reabrir este tutorial desde el botón <strong>?</strong> de la barra superior.',
                icon: 'bi-check-circle-fill',
                color: '#10b981',
                target: null,
            },
        ],

        admin: [
            {
                title: 'Panel de Administración 🛠️',
                body: 'Tienes acceso completo a la gestión de la academia. Este tutorial repasa las principales funciones disponibles.',
                icon: 'bi-shield-fill',
                color: '#3b82f6',
                target: null,
            },
            {
                title: 'Dashboard',
                body: 'El panel te da estadísticas en tiempo real: alumnos activos, sesiones esta semana, bonos activos, ingresos y más. Puedes crear sesiones rápidas directamente desde aquí.',
                icon: 'bi-speedometer2',
                color: '#3b82f6',
                target: 'a[href*="dashboard"]',
            },
            {
                title: 'Gestión de alumnos',
                body: 'En <strong>Alumnos</strong> creas nuevos alumnos (con credenciales de acceso), los editas, y accedes a su ficha completa con toda la información deportiva, documentos, bonos y anotaciones.',
                icon: 'bi-people-fill',
                color: '#8b5cf6',
                target: 'a[href*="alumnos"]',
            },
            {
                title: 'Ficha detallada del alumno',
                body: 'Desde la ficha de un alumno puedes: ver su <strong>categoría, equipo y liga</strong>, gestionar sus documentos personales, añadir anotaciones públicas e internas (con adjuntos), y consultar su historial de bonos.',
                icon: 'bi-person-lines-fill',
                color: '#06b6d4',
                target: null,
            },
            {
                title: 'Gestión de entrenadores',
                body: 'En <strong>Entrenadores</strong> creas y gestionas los perfiles del equipo técnico. Puedes asignarles grupos y ver sus estadísticas de sesiones.',
                icon: 'bi-person-badge-fill',
                color: '#f97316',
                target: 'a[href*="entrenadores"]',
            },
            {
                title: 'Clases y calendario',
                body: 'Gestiona todas las sesiones: crea sesiones puntuales o recurrentes, asigna entrenadores y alumnos, añade sede y objetivo de la sesión.',
                icon: 'bi-calendar3',
                color: '#3b82f6',
                target: 'a[href*="clases"]',
            },
            {
                title: 'Control de asistencia',
                body: 'Dentro de cada sesión, marca <strong>presente/ausente</strong> a cada alumno. Si un alumno avisó previamente su ausencia, se muestra su nota. Al completar la sesión, el bono se descuenta solo a los marcados como presente.',
                icon: 'bi-clipboard2-check-fill',
                color: '#10b981',
                target: null,
            },
            {
                title: 'Bonos',
                body: 'En <strong>Bonos</strong> gestionas los tipos de bono (paquetes de sesiones) y los asignas a alumnos individualmente. El sistema FIFO descuenta automáticamente el bono más antiguo activo.',
                icon: 'bi-ticket-perforated-fill',
                color: '#f59e0b',
                target: 'a[href*="bonos"]',
            },
            {
                title: 'Documentación',
                body: 'Crea y gestiona carpetas (públicas, personales por alumno, internas con permisos). Los alumnos ven su carpeta personal y las públicas. Las internas solo son visibles para quien tengas permiso.',
                icon: 'bi-folder-fill',
                color: '#f97316',
                target: 'a[href*="documentacion"]',
            },
            {
                title: 'Lista de compras',
                body: 'Revisa las solicitudes de material del equipo. Puedes aprobar, denegar, marcar como comprado o cancelar cada solicitud y dejar un comentario.',
                icon: 'bi-cart-fill',
                color: '#ef4444',
                target: 'a[href*="compras"]',
            },
            {
                title: 'Configuración',
                body: 'Desde <strong>Configuración</strong> gestionas: datos generales de la academia, miembros del staff (crear, roles, activar/desactivar), sedes, tipos de bono, SMTP para emails y ajustes de seguridad.',
                icon: 'bi-gear-fill',
                color: '#6b7280',
                target: 'a[href*="configuracion"]',
            },
            {
                title: 'Notificaciones y Mensajes',
                body: 'Envía notificaciones grupales a todos los alumnos, a un rol específico o a usuarios individuales. Incluye archivos adjuntos si necesitas. Los mensajes directos son privados entre dos usuarios.',
                icon: 'bi-megaphone-fill',
                color: '#10b981',
                target: 'a[href*="notificaciones"]',
            },
            {
                title: '¡Plataforma lista!',
                body: 'Tienes control total de la academia. Puedes volver a este tutorial desde el botón <strong>?</strong> en la barra superior.',
                icon: 'bi-check-circle-fill',
                color: '#10b981',
                target: null,
            },
        ],

        superadmin: [
            {
                title: 'Super Administrador ⚡',
                body: 'Tienes acceso total a todos los módulos y configuraciones de la plataforma, incluidos ajustes de seguridad y gestión completa de usuarios.',
                icon: 'bi-lightning-fill',
                color: '#f59e0b',
                target: null,
            },
            {
                title: 'Todo lo de Administrador',
                body: 'Tienes todas las funciones del rol Admin (alumnos, entrenadores, clases, bonos, documentación, configuración, compras, mensajes, notificaciones).',
                icon: 'bi-shield-fill-check',
                color: '#3b82f6',
                target: null,
            },
            {
                title: 'Gestión de Staff',
                body: 'En <strong>Configuración → Staff</strong> creas cuentas para nuevos miembros (admin, staff, coach), cambias sus roles y los activas o desactivas. Solo el Superadmin puede crear otros superadmins.',
                icon: 'bi-people-fill',
                color: '#8b5cf6',
                target: 'a[href*="configuracion"]',
            },
            {
                title: 'Ajustes de seguridad',
                body: 'En <strong>Configuración → Seguridad</strong> puedes configurar: intentos de login permitidos, expiración de sesiones y otras políticas de acceso.',
                icon: 'bi-lock-fill',
                color: '#ef4444',
                target: 'a[href*="configuracion"]',
            },
            {
                title: 'SMTP y notificaciones',
                body: 'Configura el servidor de correo para el envío de emails automáticos (bienvenida, reseteo de contraseña, avisos). Puedes enviar un email de prueba desde la propia configuración.',
                icon: 'bi-envelope-fill',
                color: '#06b6d4',
                target: 'a[href*="configuracion"]',
            },
            {
                title: 'Acceso a perfil de cualquier usuario',
                body: 'Puedes entrar en el perfil de cualquier usuario navigando a <code>/perfil/:id</code> para gestionar sus credenciales o resetear su contraseña.',
                icon: 'bi-person-gear',
                color: '#6366f1',
                target: null,
            },
            {
                title: '¡Control total!',
                body: 'Como Superadmin tienes visibilidad y control sobre toda la plataforma. Recuerda usar este poder con responsabilidad 😉',
                icon: 'bi-check-circle-fill',
                color: '#10b981',
                target: null,
            },
        ],
    };

    // ─────────────────────────────────────────────────────────────────────────
    //  ESTADO
    // ─────────────────────────────────────────────────────────────────────────

    let currentStep = 0;
    let steps       = [];
    let role        = '';
    let overlay, card, spotlightEl;

    // ─────────────────────────────────────────────────────────────────────────
    //  INIT
    // ─────────────────────────────────────────────────────────────────────────

    function init(userRole, autoShow) {
        role  = userRole;
        steps = STEPS[role] || STEPS.player;

        buildOverlay();
        attachTrigger();

        if (autoShow) {
            setTimeout(() => open(), 800);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  BUILD DOM
    // ─────────────────────────────────────────────────────────────────────────

    function buildOverlay() {
        overlay = document.createElement('div');
        overlay.id = 'jp-tutorial-overlay';
        overlay.innerHTML = `
            <div id="jp-tutorial-spotlight"></div>
            <div id="jp-tutorial-card">
                <button id="jp-tutorial-close" title="Cerrar tutorial">
                    <i class="bi bi-x-lg"></i>
                </button>
                <div id="jp-tutorial-icon-wrap">
                    <i id="jp-tutorial-icon" class="bi"></i>
                </div>
                <div id="jp-tutorial-step-label"></div>
                <h3 id="jp-tutorial-title"></h3>
                <div id="jp-tutorial-body"></div>
                <div id="jp-tutorial-nav">
                    <button id="jp-tutorial-prev">
                        <i class="bi bi-chevron-left"></i> Anterior
                    </button>
                    <div id="jp-tutorial-dots"></div>
                    <button id="jp-tutorial-next">
                        Siguiente <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
                <div id="jp-tutorial-goto-wrap">
                    <button id="jp-tutorial-goto">
                        <i class="bi bi-box-arrow-up-right"></i> Ir a esta sección
                    </button>
                </div>
            </div>`;

        const style = document.createElement('style');
        style.textContent = `
            #jp-tutorial-overlay {
                position: fixed; inset: 0; z-index: 99999;
                background: rgba(0,0,0,.65);
                display: flex; align-items: center; justify-content: center;
                backdrop-filter: blur(2px);
                animation: jp-fade-in .25s ease;
            }
            #jp-tutorial-overlay.hidden { display: none; }
            @keyframes jp-fade-in { from { opacity:0 } to { opacity:1 } }

            #jp-tutorial-spotlight {
                position: absolute; border-radius: 8px;
                box-shadow: 0 0 0 9999px rgba(0,0,0,.65);
                pointer-events: none; transition: all .35s ease;
                display: none;
            }

            #jp-tutorial-card {
                position: relative; z-index: 100000;
                background: var(--bg-card, #1e2130);
                border: 1px solid var(--border, rgba(255,255,255,.1));
                border-radius: 16px;
                padding: 32px 28px 24px;
                width: min(520px, 92vw);
                max-height: 90vh;
                overflow-y: auto;
                box-shadow: 0 24px 60px rgba(0,0,0,.5);
                animation: jp-slide-up .3s ease;
            }
            @keyframes jp-slide-up {
                from { transform: translateY(20px); opacity:0 }
                to   { transform: translateY(0);    opacity:1 }
            }

            #jp-tutorial-close {
                position: absolute; top: 14px; right: 14px;
                background: none; border: none; cursor: pointer;
                color: var(--text-muted, #9ca3af); font-size: 18px;
                padding: 4px; border-radius: 4px;
                transition: color .15s, background .15s;
            }
            #jp-tutorial-close:hover {
                color: var(--text-body, #e2e8f0);
                background: rgba(255,255,255,.08);
            }

            #jp-tutorial-icon-wrap {
                width: 64px; height: 64px; border-radius: 16px;
                display: flex; align-items: center; justify-content: center;
                margin: 0 auto 16px; font-size: 28px;
                transition: background .3s;
            }

            #jp-tutorial-step-label {
                text-align: center; font-size: 11px; font-weight: 600;
                letter-spacing: .08em; text-transform: uppercase;
                color: var(--text-muted, #9ca3af); margin-bottom: 8px;
            }

            #jp-tutorial-title {
                text-align: center; font-size: 20px; font-weight: 700;
                color: var(--text-heading, #f1f5f9);
                margin: 0 0 12px; line-height: 1.3;
            }

            #jp-tutorial-body {
                text-align: center; font-size: 14px; line-height: 1.65;
                color: var(--text-body, #cbd5e1);
                margin-bottom: 24px;
            }
            #jp-tutorial-body strong { color: var(--accent, #60a5fa); }
            #jp-tutorial-body code {
                background: rgba(255,255,255,.08); padding: 1px 6px;
                border-radius: 4px; font-size: 13px;
            }

            #jp-tutorial-nav {
                display: flex; align-items: center; justify-content: space-between; gap: 12px;
            }

            #jp-tutorial-nav button {
                display: flex; align-items: center; gap: 6px;
                padding: 8px 16px; border-radius: 8px; border: none;
                font-size: 13px; font-weight: 600; cursor: pointer;
                background: var(--bg-card-inner, rgba(255,255,255,.07));
                color: var(--text-body, #cbd5e1);
                transition: background .15s, opacity .15s;
            }
            #jp-tutorial-nav button:hover { background: rgba(255,255,255,.14); }
            #jp-tutorial-nav button:disabled { opacity:.35; cursor:default; }
            #jp-tutorial-next {
                background: var(--accent, #3b82f6) !important;
                color: #fff !important;
            }
            #jp-tutorial-next:hover { filter: brightness(1.1); }

            #jp-tutorial-dots {
                display: flex; gap: 6px; align-items: center; flex-wrap: wrap; justify-content: center;
            }
            .jp-dot {
                width: 7px; height: 7px; border-radius: 50%;
                background: rgba(255,255,255,.2); cursor: pointer;
                transition: background .2s, transform .2s;
            }
            .jp-dot.active {
                background: var(--accent, #3b82f6);
                transform: scale(1.4);
            }

            #jp-tutorial-goto-wrap {
                margin-top: 12px; text-align: center;
                display: none;
            }
            #jp-tutorial-goto-wrap.visible { display: block; }
            #jp-tutorial-goto {
                background: none; border: 1px solid var(--border, rgba(255,255,255,.15));
                border-radius: 8px; padding: 6px 14px;
                font-size: 12px; cursor: pointer; color: var(--text-muted, #9ca3af);
                transition: all .15s;
                display: inline-flex; align-items: center; gap: 6px;
            }
            #jp-tutorial-goto:hover {
                border-color: var(--accent, #60a5fa);
                color: var(--accent, #60a5fa);
            }

            #jp-tutorial-trigger {
                width: 36px; height: 36px; border-radius: 50%;
                background: rgba(255,255,255,.08);
                border: 1px solid rgba(255,255,255,.12);
                cursor: pointer; display: flex; align-items: center; justify-content: center;
                color: var(--text-muted, #9ca3af); font-size: 16px;
                transition: all .2s; flex-shrink: 0;
                margin-right: 4px;
            }
            #jp-tutorial-trigger:hover {
                background: rgba(255,255,255,.15);
                color: var(--text-body, #e2e8f0);
                border-color: rgba(255,255,255,.25);
            }
        `;
        document.head.appendChild(style);
        document.body.appendChild(overlay);
        overlay.classList.add('hidden');

        spotlightEl = overlay.querySelector('#jp-tutorial-spotlight');

        overlay.querySelector('#jp-tutorial-close').addEventListener('click', close);
        overlay.querySelector('#jp-tutorial-prev').addEventListener('click', prev);
        overlay.querySelector('#jp-tutorial-next').addEventListener('click', next);
        overlay.querySelector('#jp-tutorial-goto').addEventListener('click', goToPage);

        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) close();
        });

        document.addEventListener('keydown', (e) => {
            if (overlay.classList.contains('hidden')) return;
            if (e.key === 'Escape')      close();
            if (e.key === 'ArrowRight')  next();
            if (e.key === 'ArrowLeft')   prev();
        });
    }

    function attachTrigger() {
        const btn = document.createElement('button');
        btn.id = 'jp-tutorial-trigger';
        btn.title = 'Tutorial de la plataforma';
        btn.innerHTML = '<i class="bi bi-question-lg"></i>';
        btn.addEventListener('click', open);

        const topbarRight = document.querySelector('.topbar-right');
        if (topbarRight) {
            topbarRight.insertBefore(btn, topbarRight.firstChild);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  NAVEGACIÓN
    // ─────────────────────────────────────────────────────────────────────────

    function open(startStep) {
        currentStep = (typeof startStep === 'number') ? startStep : 0;
        overlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        render();
    }

    function close() {
        overlay.classList.add('hidden');
        document.body.style.overflow = '';
        clearSpotlight();
        localStorage.setItem('jp_tutorial_seen_' + role, '1');
    }

    function next() {
        if (currentStep < steps.length - 1) {
            currentStep++;
            render();
        } else {
            close();
        }
    }

    function prev() {
        if (currentStep > 0) {
            currentStep--;
            render();
        }
    }

    function goToPage() {
        const step = steps[currentStep];
        if (step && step.page) {
            window.location.href = step.page;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  RENDER
    // ─────────────────────────────────────────────────────────────────────────

    function render() {
        const step = steps[currentStep];
        if (!step) return;

        const iconEl  = overlay.querySelector('#jp-tutorial-icon');
        const iconWrap = overlay.querySelector('#jp-tutorial-icon-wrap');
        const label   = overlay.querySelector('#jp-tutorial-step-label');
        const title   = overlay.querySelector('#jp-tutorial-title');
        const body    = overlay.querySelector('#jp-tutorial-body');
        const prevBtn = overlay.querySelector('#jp-tutorial-prev');
        const nextBtn = overlay.querySelector('#jp-tutorial-next');
        const dotsEl  = overlay.querySelector('#jp-tutorial-dots');
        const gotoWrap = overlay.querySelector('#jp-tutorial-goto-wrap');

        // Icono y color
        iconEl.className = 'bi ' + step.icon;
        iconWrap.style.background = hexToRgba(step.color || '#3b82f6', 0.18);
        iconEl.style.color = step.color || '#3b82f6';

        // Textos
        label.textContent = 'Paso ' + (currentStep + 1) + ' de ' + steps.length;
        title.textContent = step.title;
        body.innerHTML    = step.body;

        // Botones nav
        prevBtn.disabled = (currentStep === 0);
        nextBtn.innerHTML = (currentStep === steps.length - 1)
            ? '<i class="bi bi-check-lg"></i> Finalizar'
            : 'Siguiente <i class="bi bi-chevron-right"></i>';

        // Dots
        dotsEl.innerHTML = '';
        const maxDots = Math.min(steps.length, 12);
        for (let i = 0; i < maxDots; i++) {
            const dot = document.createElement('span');
            dot.className = 'jp-dot' + (i === currentStep ? ' active' : '');
            dot.addEventListener('click', () => { currentStep = i; render(); });
            dotsEl.appendChild(dot);
        }

        // Botón "Ir a sección"
        if (step.page && window.location.pathname !== step.page) {
            gotoWrap.classList.add('visible');
        } else {
            gotoWrap.classList.remove('visible');
        }

        // Spotlight
        if (step.target) {
            const targetEl = document.querySelector(step.target);
            if (targetEl) {
                showSpotlight(targetEl);
            } else {
                clearSpotlight();
            }
        } else {
            clearSpotlight();
        }

        // Scroll al principio de la card
        const cardEl = overlay.querySelector('#jp-tutorial-card');
        cardEl.scrollTop = 0;

        // Animar card
        cardEl.style.animation = 'none';
        cardEl.offsetHeight;
        cardEl.style.animation = 'jp-slide-up .25s ease';
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  SPOTLIGHT
    // ─────────────────────────────────────────────────────────────────────────

    function showSpotlight(el) {
        const rect = el.getBoundingClientRect();
        const pad  = 8;
        spotlightEl.style.display = 'block';
        spotlightEl.style.left    = (rect.left - pad) + 'px';
        spotlightEl.style.top     = (rect.top - pad + window.scrollY) + 'px';
        spotlightEl.style.width   = (rect.width + pad * 2) + 'px';
        spotlightEl.style.height  = (rect.height + pad * 2) + 'px';
    }

    function clearSpotlight() {
        spotlightEl.style.display = 'none';
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  UTILS
    // ─────────────────────────────────────────────────────────────────────────

    function hexToRgba(hex, alpha) {
        const r = parseInt(hex.slice(1,3),16);
        const g = parseInt(hex.slice(3,5),16);
        const b = parseInt(hex.slice(5,7),16);
        return `rgba(${r},${g},${b},${alpha})`;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  API PÚBLICA
    // ─────────────────────────────────────────────────────────────────────────

    window.JPTutorial = { init, open, close };

})();
