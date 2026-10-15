-- Recordatorio de clase 25 h antes (rama feat/notificaciones-revision).
-- Ejecutar UNA vez en phpMyAdmin. Si la columna ya existe, MySQL/MariaDB avisará: es seguro ignorarlo.
-- Sin esta columna el recordatorio simplemente no se envía (el resto de avisos funciona igual).
ALTER TABLE `class_sessions`
  ADD COLUMN `reminder_sent_at` DATETIME NULL DEFAULT NULL;
