-- =============================================================
-- PRUEBAS TICKET-013 (cobertura de bono) · SOLO PRE-PRODUCCIÓN / LOCAL
-- 6 alumnos 100 % ficticios (@test.jppreparation.local, clave Test1234!).
-- NO toca ningún alumno real (ni "Aaron Alonso"). Idempotente: borra y
-- regenera solo lo suyo (alumnos de prueba + clases "TEST Cobertura%").
--
-- ⚠️ NUNCA EJECUTAR EN PRODUCCIÓN.
-- Requisito: haber ejecutado antes migraciones_bono_cobertura.sql
-- (o `php spark migrate`).
-- =============================================================

SET @admin := (SELECT id FROM users WHERE role IN ('superadmin','admin') AND status = 'active' ORDER BY id LIMIT 1);
SET @type  := (SELECT id FROM bono_types WHERE active = 1 ORDER BY id LIMIT 1);
SET @hash  := '$2y$10$Ok/DZZGHoBd5cxD/C2r5IOGcoIDk2SH5ADhdYzBDHjbxlgOgdJLA6'; -- Test1234!

-- ── 0) Limpieza de ejecuciones anteriores ────────────────────
DELETE nr FROM notification_recipients nr JOIN notifications n ON n.id = nr.notification_id
  WHERE n.body LIKE '%TEST Cobertura%' OR n.title LIKE '%TEST Cobertura%';
DELETE FROM notifications WHERE body LIKE '%TEST Cobertura%' OR title LIKE '%TEST Cobertura%';
DELETE FROM bono_movements WHERE player_id IN (SELECT id FROM users WHERE email LIKE 'pruebas.cobertura.%@test.jppreparation.local');
DELETE FROM class_session_players WHERE user_id IN (SELECT id FROM users WHERE email LIKE 'pruebas.cobertura.%@test.jppreparation.local')
   OR session_id IN (SELECT id FROM class_sessions WHERE title LIKE 'TEST Cobertura%');
DELETE FROM class_session_coaches WHERE session_id IN (SELECT id FROM class_sessions WHERE title LIKE 'TEST Cobertura%');
DELETE FROM class_sessions WHERE title LIKE 'TEST Cobertura%';
DELETE FROM classes WHERE title LIKE 'TEST Cobertura%';
DELETE FROM player_bonos WHERE player_id IN (SELECT id FROM users WHERE email LIKE 'pruebas.cobertura.%@test.jppreparation.local');
DELETE FROM users WHERE email LIKE 'pruebas.cobertura.%@test.jppreparation.local';

-- ── 1) Punto de control = hoy (para que las sesiones de hoy cuenten como deuda
--       y la de hace 10 días salga como "no reflejada") ─────────
UPDATE academy_settings SET setting_value = CURDATE(), updated_at = NOW() WHERE setting_key = 'bono_control_since';
INSERT INTO academy_settings (setting_key, setting_value, setting_type, updated_at)
  SELECT 'bono_control_since', CURDATE(), 'string', NOW()
  WHERE NOT EXISTS (SELECT 1 FROM academy_settings WHERE setting_key = 'bono_control_since');
-- Reinicia el throttle del aviso de caducidad (así sale al abrir el Dashboard)
DELETE FROM academy_settings WHERE setting_key = 'bono_expiry_alert_last_run';

-- ── 2) Alumnos ficticios ─────────────────────────────────────
INSERT INTO users (name, email, password, role, status, created_at, updated_at) VALUES
 ('TEST Cobertura 1 (bono de 2)',            'pruebas.cobertura.1@test.jppreparation.local', @hash, 'player', 'active', NOW(), NOW()),
 ('TEST Cobertura 2 (nunca tuvo bono)',      'pruebas.cobertura.2@test.jppreparation.local', @hash, 'player', 'active', NOW(), NOW()),
 ('TEST Cobertura 3 (bono caduca en 5 días)','pruebas.cobertura.3@test.jppreparation.local', @hash, 'player', 'active', NOW(), NOW()),
 ('TEST Cobertura 4 (2 deudas)',             'pruebas.cobertura.4@test.jppreparation.local', @hash, 'player', 'active', NOW(), NOW()),
 ('TEST Cobertura 5 (anterior al control)',  'pruebas.cobertura.5@test.jppreparation.local', @hash, 'player', 'active', NOW(), NOW()),
 ('TEST Cobertura 6 (bono ya caducado)',     'pruebas.cobertura.6@test.jppreparation.local', @hash, 'player', 'active', NOW(), NOW());

SET @p1 := (SELECT id FROM users WHERE email = 'pruebas.cobertura.1@test.jppreparation.local');
SET @p2 := (SELECT id FROM users WHERE email = 'pruebas.cobertura.2@test.jppreparation.local');
SET @p3 := (SELECT id FROM users WHERE email = 'pruebas.cobertura.3@test.jppreparation.local');
SET @p4 := (SELECT id FROM users WHERE email = 'pruebas.cobertura.4@test.jppreparation.local');
SET @p5 := (SELECT id FROM users WHERE email = 'pruebas.cobertura.5@test.jppreparation.local');
SET @p6 := (SELECT id FROM users WHERE email = 'pruebas.cobertura.6@test.jppreparation.local');

-- ── 3) Bonos ─────────────────────────────────────────────────
-- 1: bono de 2 sesiones vigente (caso "Aaron")
INSERT INTO player_bonos (player_id, bono_type_id, sessions_total, sessions_remaining, start_date, expires_at, created_by, created_at, updated_at)
  VALUES (@p1, @type, 2, 2, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 90 DAY), @admin, NOW(), NOW());
INSERT INTO bono_movements (player_id, bono_id, type, delta, note, actor_id, created_at)
  VALUES (@p1, LAST_INSERT_ID(), 'granted', 2, 'Prueba TICKET-013', @admin, NOW());
-- 3: bono de 4 que caduca en 5 días (aviso de caducidad + "caduca antes")
INSERT INTO player_bonos (player_id, bono_type_id, sessions_total, sessions_remaining, start_date, expires_at, created_by, created_at, updated_at)
  VALUES (@p3, @type, 4, 4, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 5 DAY), @admin, NOW(), NOW());
INSERT INTO bono_movements (player_id, bono_id, type, delta, note, actor_id, created_at)
  VALUES (@p3, LAST_INSERT_ID(), 'granted', 4, 'Prueba TICKET-013', @admin, NOW());
-- 6: bono YA caducado (hace 3 días) con 2 sesiones sin usar → probar "Ampliar caducidad"
INSERT INTO player_bonos (player_id, bono_type_id, sessions_total, sessions_remaining, start_date, expires_at, created_by, created_at, updated_at)
  VALUES (@p6, @type, 4, 2, DATE_SUB(CURDATE(), INTERVAL 60 DAY), DATE_SUB(CURDATE(), INTERVAL 3 DAY), @admin, NOW(), NOW());
INSERT INTO bono_movements (player_id, bono_id, type, delta, note, actor_id, created_at)
  VALUES (@p6, LAST_INSERT_ID(), 'granted', 4, 'Prueba TICKET-013', @admin, NOW());

-- ── 4) Clases ya dadas SIN bono ──────────────────────────────
-- 4: dos clases de HOY, presente → 2 DEUDAS abiertas
INSERT INTO class_sessions (title, session_date, start_time, end_time, status, created_by, class_format, session_type, lista_pasada_at, created_at, updated_at)
  VALUES ('TEST Cobertura clase dada sin bono 1', CURDATE(), '10:00:00', '11:00:00', 'completed', @admin, 'individual', 'coach', NOW(), NOW(), NOW());
SET @s1 := LAST_INSERT_ID();
INSERT INTO class_sessions (title, session_date, start_time, end_time, status, created_by, class_format, session_type, lista_pasada_at, created_at, updated_at)
  VALUES ('TEST Cobertura clase dada sin bono 2', CURDATE(), '11:00:00', '12:00:00', 'completed', @admin, 'individual', 'coach', NOW(), NOW(), NOW());
SET @s2 := LAST_INSERT_ID();
-- 5: una clase de hace 10 días (ANTERIOR al control) → "no reflejada", nunca deuda
INSERT INTO class_sessions (title, session_date, start_time, end_time, status, created_by, class_format, session_type, lista_pasada_at, created_at, updated_at)
  VALUES ('TEST Cobertura clase anterior al control', DATE_SUB(CURDATE(), INTERVAL 10 DAY), '10:00:00', '11:00:00', 'completed', @admin, 'individual', 'coach', NOW(), NOW(), NOW());
SET @s3 := LAST_INSERT_ID();

-- (class_session_players.id no es AUTO_INCREMENT en todos los entornos: se calcula MAX+1)
INSERT INTO class_session_players (id, session_id, user_id, attendance, created_at, updated_at)
  SELECT COALESCE(MAX(id),0)+1, @s1, @p4, 'present', NOW(), NOW() FROM class_session_players;
INSERT INTO class_session_players (id, session_id, user_id, attendance, created_at, updated_at)
  SELECT COALESCE(MAX(id),0)+1, @s2, @p4, 'present', NOW(), NOW() FROM class_session_players;
INSERT INTO class_session_players (id, session_id, user_id, attendance, created_at, updated_at)
  SELECT COALESCE(MAX(id),0)+1, @s3, @p5, 'present', NOW(), NOW() FROM class_session_players;

-- ── 5) Comprobación rápida (resultado esperado entre paréntesis) ─
SELECT 'alumnos de prueba (6)' AS comprobacion, COUNT(*) AS valor
  FROM users WHERE email LIKE 'pruebas.cobertura.%@test.jppreparation.local'
UNION ALL
SELECT 'bonos de prueba (3)', COUNT(*) FROM player_bonos WHERE player_id IN (@p1, @p3, @p6)
UNION ALL
SELECT 'deudas abiertas del alumno 4 (2)', COUNT(*)
  FROM class_session_players csp JOIN class_sessions cs ON cs.id = csp.session_id
  WHERE csp.user_id = @p4 AND cs.status = 'completed' AND csp.bono_deducted_at IS NULL AND csp.bono_resolution IS NULL
    AND cs.session_date >= (SELECT setting_value FROM academy_settings WHERE setting_key = 'bono_control_since')
UNION ALL
SELECT 'no reflejadas del alumno 5 (1)', COUNT(*)
  FROM class_session_players csp JOIN class_sessions cs ON cs.id = csp.session_id
  WHERE csp.user_id = @p5 AND cs.status = 'completed' AND csp.bono_deducted_at IS NULL
    AND cs.session_date < (SELECT setting_value FROM academy_settings WHERE setting_key = 'bono_control_since');

-- =============================================================
-- GUÍA DE PRUEBAS (entrar como admin)
--  A) Nueva clase → Recurrente (un día/semana, 1 mes) con el alumno 1
--     → panel "2 de N cubiertas"; "Limitar" crea solo 2; sin elegir nada
--       el servidor no crea ninguna; "Crear todas" (admin) crea N y avisa.
--  B) Mismo con el alumno 2 → se crean todas, marcadas "sin cubrir" (nunca tuvo bono).
--  C) Alumno 3 con serie larga → sus clases tras 5 días salen "caduca antes".
--     Abre el Dashboard → llega el aviso "Bono a punto de caducar" (una vez).
--  D) Bonos → "Deudas de sesión": el alumno 4 tiene 2 abiertas (prueba "Pagada fuera"
--     o "Condonar" en una). Luego Bonos → Asignar bono al alumno 4 → se salda sola la
--     que quede + aviso a admins.
--  E) El alumno 5 aparece SOLO en "Anteriores al control" (nunca como deuda).
--  F) Bono del alumno 6 → "Ampliar caducidad": +15/+30/+60 cuentan desde HOY;
--     fecha personalizada pasada → error. Mira el "Registro de movimientos".
--  G) Como coach/staff (si tienes uno): el panel solo deja "Crear solo las cubiertas".
-- =============================================================
