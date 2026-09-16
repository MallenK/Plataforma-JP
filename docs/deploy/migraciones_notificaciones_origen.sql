-- =============================================================
-- MIGRACIÓN — origen de las notificaciones · JP Preparation
-- Ejecutar en phpMyAdmin sobre la BD de producción (u912370917_jpapp)
-- y sobre la BD de pre-producción / demo si el `php spark migrate`
-- automático de Render no la aplica.
--
-- Corresponde a la migración CodeIgniter:
--   2026-09-16-000001_AddSourceToNotifications   (TICKET-010)
--
-- QUÉ HACE
--  1. Asegura las columnas `source_type` / `source_id` en `notifications`
--     (a dónde lleva la notificación al pulsarla: ticket o conversación).
--     Ninguna migración las creaba; en algunas BD ya existen (añadidas a
--     mano) y en otras no → IF NOT EXISTS (MariaDB / TiDB).
--  2. Rellena el origen de las notificaciones ya enviadas, que se
--     guardaron con NULL. Solo toca filas con source_type NULL, así que se
--     puede ejecutar más de una vez sin efectos.
--
-- ORDEN: ejecutar ANTES o JUSTO DESPUÉS de subir el código. Si el código
-- llega antes, las notificaciones se siguen enviando (sin enlace) y se
-- deja un aviso en el log.
--
-- (En local: docker compose exec app php spark migrate)
-- =============================================================

-- 0) Diagnóstico (opcional): ¿existen ya las columnas?
-- SHOW COLUMNS FROM `notifications` LIKE 'source%';

-- 1) Columnas
ALTER TABLE `notifications`
  ADD COLUMN IF NOT EXISTS `source_type` ENUM('ticket','conversation') NULL DEFAULT NULL AFTER `file_size`;

ALTER TABLE `notifications`
  ADD COLUMN IF NOT EXISTS `source_id` INT UNSIGNED NULL DEFAULT NULL AFTER `source_type`;

-- 2a) Notificaciones de tickets → por coincidencia EXACTA con el nº de ticket
--     (en binario: las dos tablas pueden tener collations distintas y un IN
--     normal falla con "Illegal mix of collations")
UPDATE notifications n
JOIN tickets t ON CAST(n.title AS BINARY) IN (
    CAST(CONCAT('Nuevo ticket: ', t.ticket_number) AS BINARY),
    CAST(CONCAT('Respuesta a tu ticket ', t.ticket_number) AS BINARY),
    CAST(CONCAT('Ticket ', t.ticket_number, ' actualizado') AS BINARY),
    CAST(CONCAT('Ticket asignado: ', t.ticket_number) AS BINARY),
    CAST(CONCAT('Nota interna en ', t.ticket_number) AS BINARY)
)
SET n.source_type = 'ticket', n.source_id = t.id
WHERE n.source_type IS NULL
  -- nunca a un ticket posterior al aviso (un nº borrado puede reutilizarse)
  AND n.created_at >= t.created_at;

-- 2b) "Nuevo mensaje de …" → conversación entre remitente y destinatario
UPDATE notifications n
JOIN notification_recipients nr ON nr.notification_id = n.id
JOIN conversations c
  ON c.user1_id = LEAST(n.sender_id, nr.recipient_id)
 AND c.user2_id = GREATEST(n.sender_id, nr.recipient_id)
SET n.source_type = 'conversation', n.source_id = c.id
WHERE n.source_type IS NULL
  AND n.type = 'individual'
  AND n.title LIKE 'Nuevo mensaje de %'
  AND (c.created_at IS NULL OR n.created_at >= c.created_at);

-- 3) Comprobación: cuántas quedan enlazadas
-- SELECT source_type, COUNT(*) FROM notifications GROUP BY source_type;

-- Revertir (si hiciera falta; se pierden los enlaces):
-- ALTER TABLE `notifications` DROP COLUMN `source_id`;
-- ALTER TABLE `notifications` DROP COLUMN `source_type`;
