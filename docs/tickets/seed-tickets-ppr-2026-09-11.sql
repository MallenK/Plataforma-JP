-- ════════════════════════════════════════════════════════════════
-- Seed de 2 tickets reales en la BBDD de PPR (preprod, Render/Hostinger)
-- para poder probar en PPR el fix de fix/papelera-videos-y-alerta-soporte
-- antes de mergear a main / desplegar a producción.
--
-- Ejecutar en phpMyAdmin (o `mysql`) contra la BBDD de PPR
-- (u912370917_u937091_jppre). NO ejecutar contra producción: estos
-- tickets ya existen ahí de verdad.
--
-- Requiere que existan en `users` (PPR) un usuario "Juanma Agredano Ruiz"
-- (rol player) y otro "Manel Herrera" (rol player). Si no existen en PPR,
-- créalos primero o ajusta el nombre en los WHERE de abajo.
-- ════════════════════════════════════════════════════════════════

-- 0) Comprobación previa — confirma que hay EXACTAMENTE 1 fila por nombre
--    antes de insertar. Si sale 0 o >1, ajusta el nombre y no sigas.
SELECT id, name, email, role, status
FROM users
WHERE name IN ('Juanma Agredano Ruiz', 'Manel Herrera');

-- 1) Asegurar el contador de ticket_number del año en curso
--    (mismo mecanismo que TicketModel::generateTicketNumber()).
INSERT INTO ticket_counters (`year`, `last`)
VALUES (YEAR(CURDATE()), 1)
ON DUPLICATE KEY UPDATE `last` = `last` + 1;

SET @n1 = (SELECT `last` FROM ticket_counters WHERE `year` = YEAR(CURDATE()));
SET @ticket_number_1 = CONCAT('TKT-', YEAR(CURDATE()), '-', LPAD(@n1, 5, '0'));

-- ── Ticket 1 — Juanma Agredano Ruiz — papelera para vídeos duplicados ──
INSERT INTO tickets
    (ticket_number, user_id, category, priority, scope, origin,
     title, description, status, created_at, updated_at)
SELECT
    @ticket_number_1,
    u.id,
    'mejora',
    'media',
    'academia',
    'manual',
    'Papelera para eliminar vídeos duplicados',
    'Al subir videos, sin querer he duplicado algunos.\n\nQue tenga la misma opción que hay en observaciones (papelera).\n\nUn saludo.',
    'abierto',
    '2026-09-10 09:19:00',
    '2026-09-10 09:19:00'
FROM users u
WHERE u.name = 'Juanma Agredano Ruiz'
LIMIT 1;

-- 2) Segundo contador, para el ticket 2
INSERT INTO ticket_counters (`year`, `last`)
VALUES (YEAR(CURDATE()), 1)
ON DUPLICATE KEY UPDATE `last` = `last` + 1;

SET @n2 = (SELECT `last` FROM ticket_counters WHERE `year` = YEAR(CURDATE()));
SET @ticket_number_2 = CONCAT('TKT-', YEAR(CURDATE()), '-', LPAD(@n2, 5, '0'));

-- ── Ticket 2 — Manel Herrera — error automático al enviar mensaje ──
-- Reproduce tal cual el ticket real (reporte automático de Mensajes,
-- ver MensajesController::reportError()). Referencia de log 39A6D2
-- corresponde al servidor de PRODUCCIÓN, no va a encontrarse en los
-- logs de PPR — es solo para conservar el texto original en la ficha.
INSERT INTO tickets
    (ticket_number, user_id, category, priority, scope, origin,
     title, description, status, created_at, updated_at)
SELECT
    @ticket_number_2,
    u.id,
    'bug',
    'alta',
    'academia',
    'manual',
    'Error automático — Mensajes: Enviar mensaje',
    CONCAT(
        'Reporte automático generado desde Mensajes.\n\n',
        'Acción que falló: Enviar mensaje\n',
        'Usuario: Manel Herrera  (—, rol: player)\n',
        'URL: https://app.jppreparation.com/mensajes\n',
        'Fecha: 2026-09-02 22:27:47\n',
        'Referencia de error del servidor: 39A6D2\n',
        'Detalle técnico: Ha ocurrido un error inesperado al enviar el mensaje.\n',
        'Navegador: Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6.1 Mobile/15E148 Safari/604.1'
    ),
    'abierto',
    '2026-09-02 22:27:47',
    '2026-09-02 22:27:47'
FROM users u
WHERE u.name = 'Manel Herrera'
LIMIT 1;

-- 3) Verificación final
SELECT id, ticket_number, user_id, category, priority, status, title, created_at
FROM tickets
WHERE ticket_number IN (@ticket_number_1, @ticket_number_2);
