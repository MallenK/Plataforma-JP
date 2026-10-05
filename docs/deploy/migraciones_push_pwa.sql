-- PWA + notificaciones push (rama feat/pwa-notificaciones-push) — phpMyAdmin / MariaDB
-- Equivale a la migración 2026-10-05-000001_CreatePushSubscriptions. Idempotente.
-- Además hay que añadir al .env del servidor (php spark push:vapid, o a mano):
--   vapid.publicKey / vapid.privateKey / vapid.subject
-- y subir: app/, public/sw.js, public/manifest.webmanifest, public/offline.html,
-- public/assets/js/pwa.js, public/assets/img/pwa/ y public/.htaccess.

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

-- Registrar la migración (si se aplica a mano, para que `spark migrate` no la repita):
-- INSERT INTO migrations (version, class, `group`, namespace, time, batch)
-- VALUES ('2026-10-05-000001', 'App\Database\Migrations\CreatePushSubscriptions', 'default', 'App', UNIX_TIMESTAMP(), (SELECT COALESCE(MAX(b.batch),0)+1 FROM (SELECT batch FROM migrations) b));

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
-- INSERT INTO migrations (version, class, `group`, namespace, time, batch)
-- VALUES ('2026-10-05-000002', 'App\Database\Migrations\CreateNotificationPreferences', 'default', 'App', UNIX_TIMESTAMP(), (SELECT COALESCE(MAX(b.batch),0)+1 FROM (SELECT batch FROM migrations) b));
