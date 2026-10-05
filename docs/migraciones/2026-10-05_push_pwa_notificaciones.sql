-- ============================================================================
-- PWA + notificaciones push + preferencias de notificación  (v2.0.0)
-- Copiar y pegar COMPLETO en phpMyAdmin (pestaña SQL) de la base de datos de PPR.
-- Es idempotente: se puede ejecutar más de una vez sin romper nada.
-- No toca datos existentes: solo crea 2 tablas nuevas.
-- Equivale a las migraciones 2026-10-05-000001 y 2026-10-05-000002.
--
-- Además de esto, en el servidor hay que definir en las variables de entorno
-- (Render → Environment) las claves de push, generadas con `php spark push:vapid`:
--   vapid.publicKey, vapid.privateKey, vapid.subject
-- Sin ellas todo funciona igual, solo que no se envían avisos push.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `push_subscriptions` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       INT NOT NULL,
  `endpoint`      TEXT NOT NULL,
  `endpoint_hash` CHAR(64) NOT NULL,
  `p256dh`        VARCHAR(255) NOT NULL,
  `auth`          VARCHAR(255) NOT NULL,
  `user_agent`    VARCHAR(255) NULL,
  `failures`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`    DATETIME NULL,
  `last_used_at`  DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `endpoint_hash` (`endpoint_hash`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `push_subscriptions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ─────────────────────────────────────────────────────────────────────
-- Preferencias de notificación (migración 2026-10-05-000002_CreateNotificationPreferences)
-- Sin fila = todo activado, así que no hace falta rellenar nada.
CREATE TABLE IF NOT EXISTS `notification_preferences` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT NOT NULL,
  `category`   VARCHAR(20) NOT NULL,
  `in_app`     TINYINT(1) NOT NULL DEFAULT 1,
  `push`       TINYINT(1) NOT NULL DEFAULT 1,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id_category` (`user_id`, `category`),
  CONSTRAINT `notification_preferences_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Si más adelante se ejecuta `php spark migrate`, no hay conflicto: las migraciones usan CREATE TABLE IF NOT EXISTS.
