-- =============================================================
-- MIGRACIÓN — adjuntos en observaciones de sesión de clase
-- Ejecutar en phpMyAdmin sobre la BD de pre-producción (Render / 2ª
-- BD Hostinger u912370917_u937091_jppre) y, cuando corresponda, sobre
-- la BD de producción (u912370917_jpapp).
--
-- Corresponde a la migración CodeIgniter:
--   2026-09-17-000002_CreateClassSessionAttachments
--
-- Crea la tabla `class_session_attachments`: fotos/vídeos/documentos
-- adjuntos a las observaciones de una sesión de clase.
--   `player_id` NULL   -> adjunto general de la sesión (toda la clase)
--   `player_id` con FK -> adjunto ligado a la observación individual
--                         de ese jugador (class_session_players.id,
--                         NO users.id)
--
-- (En local: docker compose exec app php spark migrate)
-- =============================================================

CREATE TABLE IF NOT EXISTS `class_session_attachments` (
  `id`          int unsigned NOT NULL AUTO_INCREMENT,
  `session_id`  int unsigned NOT NULL,
  `player_id`   int unsigned DEFAULT NULL,
  `file_path`   varchar(500) COLLATE utf8mb4_general_ci NOT NULL,
  `file_name`   varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `file_size`   int unsigned NOT NULL,
  `file_mime`   varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  -- users.id es INT CON SIGNO (ver CLAUDE.md) -> sin unsigned aquí,
  -- y sin FK a users por el mismo motivo que el resto de tablas de
  -- adjuntos del proyecto (ticket_attachments, messages, etc.).
  `uploaded_by` int DEFAULT NULL,
  `created_at`  datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `session_id` (`session_id`),
  KEY `player_id` (`player_id`),
  CONSTRAINT `class_session_attachments_session_id_foreign`
    FOREIGN KEY (`session_id`) REFERENCES `class_sessions` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `class_session_attachments_player_id_foreign`
    FOREIGN KEY (`player_id`) REFERENCES `class_session_players` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Revertir (solo si hiciera falta y no se ha guardado ya ningún adjunto):
-- DROP TABLE IF EXISTS `class_session_attachments`;
