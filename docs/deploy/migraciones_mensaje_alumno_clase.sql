-- Mensaje del alumno sobre una clase (rama feat/alumno-clases-simple).
-- Ejecutar UNA vez en phpMyAdmin. Si las columnas ya existen, MySQL/MariaDB avisará: es seguro ignorarlo.
ALTER TABLE `class_session_players`
  ADD COLUMN `student_message` VARCHAR(600) NULL DEFAULT NULL AFTER `student_noted_at`,
  ADD COLUMN `student_message_at` DATETIME NULL DEFAULT NULL AFTER `student_message`;
