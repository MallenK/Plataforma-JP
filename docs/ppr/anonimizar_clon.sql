-- ═════════════════════════════════════════════════════════════════════════════
--  Anonimiza una COPIA de producción para usarla en PPR / local.
--
--  ⚠️  NUNCA en producción ni directamente en PPR: se ejecuta sobre la BD
--      TEMPORAL donde `remoto_clonar.sh` acaba de volcar prod. Si la BD actual
--      es la de prod o la de PPR, la primera sentencia falla y mysql se detiene.
--
--  Antes de este fichero, el script define @ppr_hash (bcrypt de la contraseña
--  común que tendrán todos los usuarios en PPR).
--
--  Qué cambia:
--   - users: nombre falso (listas de BulkDemoDataSeeder), email <rol>.<id>@ppr.test,
--     contraseña común, sin avatar.
--   - player_profiles: equipo/liga falsos, fecha de nacimiento movida dentro del
--     mismo año (la categoría no cambia), notas médicas BORRADAS.
--   - Cualquier texto libre (notas, observaciones, mensajes, tickets, avisos,
--     emails, anotaciones...) → texto de prueba. Se conserva si estaba vacío o no.
--   - Títulos de clases → "Individual · <alumno falso>" / "Duo · <alumno falso>".
--   - Nombres de ficheros adjuntos y documentos → genéricos (los ficheros no se copian).
--   - auth_events: identificador, IP y navegador falsos. password_resets: vacío.
--   - academy_settings: se vacían claves que parezcan secretos (smtp, api, key...).
--  Qué NO cambia: ids, fechas de clases y bonos, precios, tipos de bono, estados,
--  asistencias, sedes, relaciones. Por eso PPR queda igual que prod.
-- ═════════════════════════════════════════════════════════════════════════════

-- Guardia: aborta si estamos en prod o en PPR, o si falta la contraseña común.
DELIMITER //
BEGIN NOT ATOMIC
  IF DATABASE() IN ('u912370917_jpapp', 'u912370917_u937091_jppre') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ABORTADO: esta BD es prod o PPR, no la temporal';
  END IF;
  IF @ppr_hash IS NULL OR @ppr_hash NOT LIKE '$2y$%' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ABORTADO: falta @ppr_hash';
  END IF;
END//
DELIMITER ;

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_SAFE_UPDATES = 0;
SET @sal = SHA2(CONCAT(RAND(), NOW(6), CONNECTION_ID()), 256);
SET @txt = 'Texto de prueba (anonimizado).';

-- ── Usuarios ─────────────────────────────────────────────────────────────────
UPDATE users SET
  name = CASE WHEN role = 'player' THEN CONCAT(
           ELT(1 + (id * 7) % 40,
               'Pau','Marc','Nil','Biel','Eric','Bruno','Aleix','Arnau','Gerard','Oriol',
               'Martina','Laia','Emma','Julia','Aina','Carla','Clàudia','Judith','Paula','Alba',
               'Hugo','Dani','Adrià','Iker','Rayan','Youssef','Mohamed','Diego','Mateo','Àlex',
               'Leo','Izan','Jan','Enzo','Roc','Guim','Ivet','Naia','Ona','Vera'),
           ' ',
           ELT(1 + id % 20,
               'García','Martínez','López','Fernández','Pérez','Gómez','Sánchez','Romero','Navarro','Torres',
               'Vázquez','Serra','Prat','Vila','Balcells','Bonet','Costa','Marín','Ortega','Ramos'),
           ' ',
           ELT(1 + (id * 3 + 1) % 20,
               'García','Martínez','López','Fernández','Pérez','Gómez','Sánchez','Romero','Navarro','Torres',
               'Vázquez','Serra','Prat','Vila','Balcells','Bonet','Costa','Marín','Ortega','Ramos'))
         ELSE CONCAT(
           ELT(1 + id % 11, 'Marc','Laura','David','Anna','Jordi','Núria','Alex','Cristina','Montse','Ferran','Silvia'),
           ' ',
           ELT(1 + (id * 5 + 2) % 11, 'Puig','Ferrer','Soler','Vidal','Roca','Camps','Riera','Bosch','Pla','Coll','Torres'))
         END,
  email = CONCAT(CASE role WHEN 'player' THEN 'alumno' WHEN 'coach' THEN 'entrenador' ELSE role END,
                 '.', id, '@ppr.test'),
  password = @ppr_hash,
  password_changed_at = NOW(),
  must_change_password = 0,
  avatar = NULL;

UPDATE player_profiles SET
  team   = IF(team   IS NULL OR TRIM(team)   = '', team,
              ELT(1 + player_id % 8, 'CF Sant Vicenç A','CF Sant Vicenç B','UE Santboiana','CE Sabadell',
                  'CF Vallirana','FC Martorell','UD Sant Boi','CF Pallejà')),
  league = IF(league IS NULL OR TRIM(league) = '', league,
              ELT(1 + player_id % 4, 'Primera Divisió','Segona Divisió','Preferent','Lliga Nacional')),
  medical_notes = NULL,
  birth_date = IF(birth_date IS NULL OR YEAR(birth_date) < 1900, birth_date,
                  DATE_ADD(MAKEDATE(YEAR(birth_date), 1), INTERVAL (player_id * 37) % 365 DAY));

-- ── Clases y sesiones ────────────────────────────────────────────────────────
UPDATE class_sessions cs
LEFT JOIN (SELECT session_id, MIN(user_id) AS uid FROM class_session_players GROUP BY session_id) p
       ON p.session_id = cs.id
LEFT JOIN users u ON u.id = p.uid
SET cs.title = CONCAT(IF(cs.class_format = 'pareja', 'Duo', 'Individual'),
                      IF(u.name IS NULL, '', CONCAT(' · ', u.name))),
    cs.location_custom = IF(cs.location_custom IS NULL OR TRIM(cs.location_custom) = '', cs.location_custom, 'Ubicación de prueba'),
    cs.focus      = IF(cs.focus      IS NULL OR TRIM(cs.focus)      = '', cs.focus,      @txt),
    cs.pre_notes  = IF(cs.pre_notes  IS NULL OR TRIM(cs.pre_notes)  = '', cs.pre_notes,  @txt),
    cs.post_notes = IF(cs.post_notes IS NULL OR TRIM(cs.post_notes) = '', cs.post_notes, @txt);

UPDATE classes c
LEFT JOIN (SELECT class_id, MIN(id) AS sid FROM class_sessions WHERE class_id IS NOT NULL GROUP BY class_id) x
       ON x.class_id = c.id
LEFT JOIN class_sessions s ON s.id = x.sid
SET c.title = COALESCE(s.title, IF(c.class_format = 'pareja', 'Duo', 'Individual')),
    c.description   = IF(c.description   IS NULL OR TRIM(c.description)   = '', c.description,   @txt),
    c.default_focus = IF(c.default_focus IS NULL OR TRIM(c.default_focus) = '', c.default_focus, @txt),
    c.default_location_custom = IF(c.default_location_custom IS NULL OR TRIM(c.default_location_custom) = '',
                                   c.default_location_custom, 'Ubicación de prueba');

UPDATE class_session_players SET
  absence_reason = IF(absence_reason IS NULL OR TRIM(absence_reason) = '', absence_reason, 'Motivo de prueba'),
  absence_notes  = IF(absence_notes  IS NULL OR TRIM(absence_notes)  = '', absence_notes,  'Nota de ausencia de prueba'),
  student_note   = IF(student_note   IS NULL OR TRIM(student_note)   = '', student_note,   'Aviso del alumno (prueba)'),
  pre_obs        = IF(pre_obs        IS NULL OR TRIM(pre_obs)        = '', pre_obs,        @txt),
  post_obs       = IF(post_obs       IS NULL OR TRIM(post_obs)       = '', post_obs,       @txt);

UPDATE class_session_attachments SET
  file_name = CONCAT('adjunto_', id, IF(LOCATE('.', file_name) > 0, CONCAT('.', SUBSTRING_INDEX(file_name, '.', -1)), ''));

-- ── Bonos ────────────────────────────────────────────────────────────────────
UPDATE player_bonos   SET notes = IF(notes IS NULL OR TRIM(notes) = '', notes, 'Nota de prueba (anonimizada).');
UPDATE bono_movements SET note  = IF(note  IS NULL OR TRIM(note)  = '', note,  CONCAT('Nota de prueba (', type, ')'));

-- ── Anotaciones, documentos ──────────────────────────────────────────────────
UPDATE player_annotations SET content = IF(content IS NULL OR TRIM(content) = '', content, @txt);

UPDATE documents SET
  name_original = CONCAT('documento_', id, IF(extension IS NULL OR extension = '', '', CONCAT('.', extension))),
  description   = IF(description IS NULL OR TRIM(description) = '', description, @txt);

UPDATE document_folders f
LEFT JOIN users u ON u.id = f.owner_id
SET f.name = CONCAT('Carpeta de ', COALESCE(u.name, CONCAT('usuario ', f.owner_id))),
    f.slug = CONCAT('personal-', f.id)
WHERE f.type = 'personal';

TRUNCATE TABLE document_folders_bak;

-- ── Mensajes, notificaciones, emails ─────────────────────────────────────────
UPDATE messages SET
  body = IF(body IS NULL OR TRIM(body) = '', body,
            ELT(1 + id % 12,
                'Hola, ¿qué tal fue el entreno?', 'Perfecto, nos vemos el martes.',
                'Hoy no podrá venir, mañana confirmo.', 'Gracias por avisar.',
                '¿Podemos cambiar la hora de la sesión?', 'Sí, sin problema.',
                'Te paso el resumen de la sesión.', 'Muy buen trabajo hoy.',
                '¿Cuántas sesiones le quedan del bono?', 'Le quedan dos, lo renovamos la semana que viene.',
                'Recordad traer las botas de césped.', 'Entendido, gracias.')),
  file_name = IF(file_name IS NULL, NULL,
                 CONCAT('adjunto_', id, IF(LOCATE('.', file_name) > 0, CONCAT('.', SUBSTRING_INDEX(file_name, '.', -1)), '')));

UPDATE notifications SET
  title = CASE source_type
            WHEN 'ticket'       THEN CONCAT('Actualización de ticket #', COALESCE(source_id, id))
            WHEN 'conversation' THEN 'Nuevo mensaje'
            WHEN 'class'        THEN 'Cambio en una clase'
            ELSE CONCAT('Aviso de prueba #', id) END,
  body = IF(body IS NULL OR TRIM(body) = '', body, @txt),
  file_name = IF(file_name IS NULL, NULL,
                 CONCAT('adjunto_', id, IF(LOCATE('.', file_name) > 0, CONCAT('.', SUBSTRING_INDEX(file_name, '.', -1)), '')));

UPDATE email_log SET
  subject   = CONCAT('Email de prueba #', id),
  message   = IF(message IS NULL OR TRIM(message) = '', message, @txt),
  error_msg = IF(error_msg IS NULL OR TRIM(error_msg) = '', error_msg, 'Error de prueba');

-- ── Tickets ──────────────────────────────────────────────────────────────────
UPDATE tickets SET
  title       = CONCAT('Ticket de prueba ', COALESCE(ticket_number, id)),
  description = IF(description IS NULL OR TRIM(description) = '', description, @txt),
  context     = NULL;
UPDATE ticket_replies    SET body = IF(body IS NULL OR TRIM(body) = '', body, 'Respuesta de prueba.');
UPDATE ticket_status_log SET note = IF(note IS NULL OR TRIM(note) = '', note, 'Nota de prueba.');
UPDATE ticket_events SET
  from_value = IF(from_value IS NULL OR from_value REGEXP '^[a-z0-9_]*$', from_value, '(anonimizado)'),
  to_value   = IF(to_value   IS NULL OR to_value   REGEXP '^[a-z0-9_]*$', to_value,   '(anonimizado)');
UPDATE ticket_attachments SET
  file_name = CONCAT('adjunto_', id, IF(LOCATE('.', file_name) > 0, CONCAT('.', SUBSTRING_INDEX(file_name, '.', -1)), ''));

-- ── Compras (vacía hoy, por si acaso) ────────────────────────────────────────
UPDATE purchase_requests SET
  name = CONCAT('Compra de prueba #', id), description = NULL, url = NULL,
  admin_comment = IF(admin_comment IS NULL, NULL, 'Comentario de prueba');

-- ── Seguridad y registros ────────────────────────────────────────────────────
UPDATE auth_events SET
  identifier = IF(identifier IS NULL, NULL, CONCAT('id.', LEFT(SHA2(CONCAT(@sal, identifier), 256), 10), '@ppr.test')),
  ip_address = CONCAT('10.0.', (id DIV 250) % 250, '.', id % 250),
  user_agent = 'Mozilla/5.0 (PPR anonimizado)',
  meta = NULL;
DELETE FROM password_resets;
UPDATE logs SET data = NULL;

-- ── Ajustes: fuera secretos (claves SMTP, API, tokens...) ────────────────────
UPDATE academy_settings
SET setting_value = ''
WHERE setting_key REGEXP '(^smtp_)|secret|token|api_?key|resend|vapid' AND setting_key NOT LIKE 'sec\_%';
UPDATE academy_settings_bak_20261006
SET setting_value = ''
WHERE setting_key REGEXP '(^smtp_)|secret|token|api_?key|resend|vapid' AND setting_key NOT LIKE 'sec\_%';

SET FOREIGN_KEY_CHECKS = 1;

-- Comprobaciones (el script las vuelve a mirar, aquí solo informan)
SELECT 'emails no @ppr.test'  AS control, COUNT(*) AS n FROM users WHERE email NOT LIKE '%@ppr.test'
UNION ALL SELECT 'notas médicas',          COUNT(*) FROM player_profiles WHERE medical_notes IS NOT NULL
UNION ALL SELECT 'password_resets',        COUNT(*) FROM password_resets
UNION ALL SELECT 'usuarios sin la contraseña PPR', COUNT(*) FROM users WHERE password <> @ppr_hash;
