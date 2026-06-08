-- =============================================================
-- SCRIPT DE VACIADO DE CONTENIDO - Plataforma JP
-- Elimina usuarios con rol staff, player, coach y todas las clases.
-- NUNCA toca usuarios con rol admin o superadmin.
-- Ejecutar en orden; desactiva FK checks temporalmente para
-- poder vaciar en bloque sin depender del orden exacto de FKs
-- sin CASCADE.
-- =============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------
-- 1. TABLAS HIJAS DE class_sessions
-- -------------------------------------------------------------
DELETE FROM class_session_players;
DELETE FROM class_session_coaches;

-- -------------------------------------------------------------
-- 2. SESSION ATTENDANCE (ligada a sessions antiguas)
-- -------------------------------------------------------------
DELETE FROM session_attendance;

-- -------------------------------------------------------------
-- 3. CLASS SESSIONS y CLASSES
-- -------------------------------------------------------------
DELETE FROM class_sessions;
DELETE FROM classes;

-- -------------------------------------------------------------
-- 4. SESSIONS (entrenamientos individuales legacy)
-- -------------------------------------------------------------
DELETE FROM sessions;

-- -------------------------------------------------------------
-- 5. DATOS DE JUGADOR
-- -------------------------------------------------------------
DELETE FROM player_annotations;
DELETE FROM player_metrics;
DELETE FROM player_plans;
DELETE FROM player_bonos;
DELETE FROM player_profiles;

-- -------------------------------------------------------------
-- 6. OBSERVACIONES
-- -------------------------------------------------------------
DELETE FROM observations;

-- -------------------------------------------------------------
-- 7. EVENTOS Y DERIVADOS
-- (events.created_by tiene CASCADE pero otras tablas no)
-- -------------------------------------------------------------
DELETE FROM event_results;
DELETE FROM event_notifications;
DELETE FROM event_confirmations;
DELETE FROM event_team_members;
DELETE FROM event_teams;
DELETE FROM external_participants;
DELETE FROM event_participants;
DELETE FROM events;

-- -------------------------------------------------------------
-- 8. MENSAJERÍA Y NOTIFICACIONES
-- -------------------------------------------------------------
DELETE FROM messages;
DELETE FROM conversations;
DELETE FROM notification_recipients;
DELETE FROM notifications;

-- -------------------------------------------------------------
-- 9. PURCHASE REQUESTS
-- -------------------------------------------------------------
DELETE FROM purchase_requests;

-- -------------------------------------------------------------
-- 10. LOGS
-- -------------------------------------------------------------
DELETE FROM logs;
DELETE FROM email_log;

-- -------------------------------------------------------------
-- 11. PASSWORD RESETS
-- -------------------------------------------------------------
DELETE FROM password_resets;

-- -------------------------------------------------------------
-- 12. USUARIOS (staff, player, coach) — NUNCA admin/superadmin
-- -------------------------------------------------------------
DELETE FROM users
WHERE role IN ('staff', 'player', 'coach');

-- -------------------------------------------------------------
-- Reactivar FK checks
-- -------------------------------------------------------------
SET FOREIGN_KEY_CHECKS = 1;

-- Verificación final: debe devolver 0 filas con rol no-admin
SELECT id, name, email, role
FROM users
WHERE role NOT IN ('admin', 'superadmin');
