-- ═════════════════════════════════════════════════════════════════════════════
--  Extracción ANONIMIZADA de producción para diseñar el módulo de Finanzas
--  (v2.0.0). Para ejecutar en phpMyAdmin → BD u912370917_jpapp → pestaña SQL.
--
--  SOLO LECTURA: son dos SELECT. No crean, modifican ni borran nada.
--
--  Qué sale y qué no:
--   - Cada alumno sale como un código (A-xxxxxxxx) y cada miembro del equipo
--     como E-xxxxxxxx. El código se calcula con una "sal" que escribes tú y no
--     compartes: sin ella no se puede volver al usuario real.
--   - NO salen nombres, emails, teléfonos, equipos, fechas de nacimiento ni
--     ningún texto libre (notas, motivos de ausencia, observaciones). De esos
--     textos solo sale "tiene texto sí/no" y, en las notas del bono, unas
--     palabras clave de pago detectadas aquí dentro (efectivo, bizum, gratis...).
--   - SÍ salen precios, fechas de clases y bonos, estados de asistencia y los
--     minutos de antelación con que se avisó una ausencia.
--   - Si alguna fila contuviera una "@", se sustituye por {"bloqueado":...}.
-- ═════════════════════════════════════════════════════════════════════════════


-- ─────────────────────────────────────────────────────────────────────────────
--  PASO 1 — Estructura de la BD (columnas, claves, nº de filas). Sin datos de
--  personas. Ejecuta SOLO esta consulta, y en el resultado:
--  "Exportar" (abajo, en "Operaciones sobre los resultados") → formato CSV →
--  Continuar. Guarda el fichero como paso1_estructura.csv
-- ─────────────────────────────────────────────────────────────────────────────

SELECT 'version' AS tipo, NULL AS tabla, NULL AS nombre, VERSION() AS detalle, NULL AS extra1, NULL AS extra2
UNION ALL
SELECT 'columna', TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, CONCAT_WS(' ', COLUMN_KEY, EXTRA, CONCAT('default=', COLUMN_DEFAULT))
FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
UNION ALL
SELECT 'fk', k.TABLE_NAME, k.COLUMN_NAME, CONCAT(k.REFERENCED_TABLE_NAME, '.', k.REFERENCED_COLUMN_NAME),
       CONCAT('ON DELETE ', r.DELETE_RULE), CONCAT('ON UPDATE ', r.UPDATE_RULE)
FROM information_schema.KEY_COLUMN_USAGE k
JOIN information_schema.REFERENTIAL_CONSTRAINTS r
  ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
WHERE k.TABLE_SCHEMA = DATABASE() AND k.REFERENCED_TABLE_NAME IS NOT NULL
UNION ALL
SELECT 'tabla', TABLE_NAME, ENGINE, TABLE_ROWS, TABLE_COLLATION, AUTO_INCREMENT
FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE();


-- ─────────────────────────────────────────────────────────────────────────────
--  PASO 2 — Datos anonimizados.
--  1) En la línea de abajo cambia CAMBIA_ESTO por 20 o más caracteres al azar
--     (aporrea el teclado). NO me la pases ni la guardes: es lo que impide
--     deshacer los códigos. Si no la cambias, la consulta solo devuelve un
--     aviso de error.
--  2) Ejecuta SOLO esta consulta (desde WITH hasta el ; final).
--  3) "Exportar" → formato CSV → Continuar. Guarda como paso2_datos.csv
--     (phpMyAdmin vuelve a ejecutar la consulta al exportar: es normal).
-- ─────────────────────────────────────────────────────────────────────────────

WITH cfg AS (SELECT 'CAMBIA_ESTO' AS sal),
filas AS (

  SELECT _utf8mb4'bono_types' COLLATE utf8mb4_unicode_ci AS tabla, CONVERT(JSON_OBJECT(
           'id', bt.id, 'nombre', bt.name, 'sesiones', bt.sessions, 'precio', bt.price,
           'validez_dias', bt.validity_days, 'activo', bt.active,
           'creado', bt.created_at, 'actualizado', bt.updated_at) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS fila
  FROM bono_types bt

  UNION ALL
  SELECT _utf8mb4'player_bonos' COLLATE utf8mb4_unicode_ci, CONVERT(JSON_OBJECT(
           'id', pb.id,
           'alumno', IF(pb.player_id IS NULL, NULL, CONCAT('A-', LEFT(SHA2(CONCAT(cfg.sal, 'u', pb.player_id), 256), 8))),
           'tipo_id', pb.bono_type_id, 'sesiones_total', pb.sessions_total, 'sesiones_quedan', pb.sessions_remaining,
           'inicio', pb.start_date, 'caduca', pb.expires_at, 'creado_por_rol', cu.role,
           'tiene_notas', (pb.notes IS NOT NULL AND TRIM(pb.notes) <> ''),
           'notas_claves', CONCAT_WS(',',
               IF(LOWER(COALESCE(pb.notes, '')) REGEXP 'efectiv|cash',               'efectivo', NULL),
               IF(LOWER(COALESCE(pb.notes, '')) REGEXP 'bizum',                      'bizum', NULL),
               IF(LOWER(COALESCE(pb.notes, '')) REGEXP 'transf|banc',                'transferencia', NULL),
               IF(LOWER(COALESCE(pb.notes, '')) REGEXP 'tarjet|tpv|datafon',         'tarjeta', NULL),
               IF(LOWER(COALESCE(pb.notes, '')) REGEXP 'pagad|cobrad|abonad',        'pagado', NULL),
               IF(LOWER(COALESCE(pb.notes, '')) REGEXP 'pendient|debe|deuda',        'pendiente', NULL),
               IF(LOWER(COALESCE(pb.notes, '')) REGEXP 'regal|gratis|cortes|invit',  'gratuito', NULL),
               IF(LOWER(COALESCE(pb.notes, '')) REGEXP 'descuent|oferta|promo|herman', 'descuento', NULL),
               IF(LOWER(COALESCE(pb.notes, '')) REGEXP '[0-9]+([.,][0-9]+)? ?(€|eur)', 'importe', NULL)),
           'creado', pb.created_at, 'actualizado', pb.updated_at) USING utf8mb4) COLLATE utf8mb4_unicode_ci
  FROM player_bonos pb CROSS JOIN cfg
  LEFT JOIN users cu ON cu.id = pb.created_by

  UNION ALL
  SELECT _utf8mb4'bono_movements' COLLATE utf8mb4_unicode_ci, CONVERT(JSON_OBJECT(
           'id', bm.id, 'alumno', CONCAT('A-', LEFT(SHA2(CONCAT(cfg.sal, 'u', bm.player_id), 256), 8)),
           'bono_id', bm.bono_id, 'bono_relacionado_id', bm.related_bono_id, 'sesion_id', bm.session_id,
           'tipo', bm.type, 'delta', bm.delta, 'actor_rol', au.role,
           'tiene_nota', (bm.note IS NOT NULL AND TRIM(bm.note) <> ''), 'creado', bm.created_at) USING utf8mb4) COLLATE utf8mb4_unicode_ci
  FROM bono_movements bm CROSS JOIN cfg
  LEFT JOIN users au ON au.id = bm.actor_id

  UNION ALL
  SELECT _utf8mb4'classes' COLLATE utf8mb4_unicode_ci, CONVERT(JSON_OBJECT(
           'id', c.id, 'tipo', c.type, 'dias', c.recurrence_days,
           'desde', c.recurrence_start, 'hasta', c.recurrence_end,
           'hora_inicio', c.recurrence_time_start, 'hora_fin', c.recurrence_time_end,
           'formato', c.class_format, 'sede_id', c.default_location_id,
           'renovada_de', c.renewed_from_class_id, 'renovada_a', c.renewed_to_class_id, 'creado', c.created_at) USING utf8mb4) COLLATE utf8mb4_unicode_ci
  FROM classes c

  UNION ALL
  SELECT _utf8mb4'class_sessions' COLLATE utf8mb4_unicode_ci, CONVERT(JSON_OBJECT(
           'id', cs.id, 'clase_id', cs.class_id, 'fecha', cs.session_date,
           'hora_inicio', cs.start_time, 'hora_fin', cs.end_time, 'estado', cs.status,
           'tipo_sesion', cs.session_type, 'formato', cs.class_format, 'sede_id', cs.location_id,
           'lista_pasada', cs.lista_pasada_at, 'creado_por_rol', cu.role,
           'creado', cs.created_at, 'actualizado', cs.updated_at) USING utf8mb4) COLLATE utf8mb4_unicode_ci
  FROM class_sessions cs
  LEFT JOIN users cu ON cu.id = cs.created_by

  UNION ALL
  SELECT _utf8mb4'class_session_coaches' COLLATE utf8mb4_unicode_ci, CONVERT(JSON_OBJECT(
           'sesion_id', csc.session_id,
           'equipo', CONCAT('E-', LEFT(SHA2(CONCAT(cfg.sal, 'u', csc.user_id), 256), 8)), 'rol', u.role) USING utf8mb4) COLLATE utf8mb4_unicode_ci
  FROM class_session_coaches csc CROSS JOIN cfg
  LEFT JOIN users u ON u.id = csc.user_id

  UNION ALL
  SELECT _utf8mb4'class_session_players' COLLATE utf8mb4_unicode_ci, CONVERT(JSON_OBJECT(
           'id', csp.id, 'sesion_id', csp.session_id,
           'alumno', CONCAT('A-', LEFT(SHA2(CONCAT(cfg.sal, 'u', csp.user_id), 256), 8)),
           'entrenador', IF(csp.coach_id IS NULL, NULL, CONCAT('E-', LEFT(SHA2(CONCAT(cfg.sal, 'u', csp.coach_id), 256), 8))),
           'asistencia', csp.attendance, 'cobertura', csp.bono_coverage, 'resolucion', csp.bono_resolution,
           'bono_descontado', csp.bono_deducted_at, 'bono_descontado_de', csp.bono_deducted_from_id,
           'resuelto', csp.bono_resolved_at, 'resuelto_por_rol', ru.role,
           'respondido', csp.responded_at, 'aviso_alumno', csp.student_noted_at,
           'aviso_minutos_antes', TIMESTAMPDIFF(MINUTE, csp.student_noted_at, TIMESTAMP(cs.session_date, cs.start_time)),
           'tiene_motivo', (csp.absence_reason IS NOT NULL AND TRIM(csp.absence_reason) <> ''),
           'tiene_nota_ausencia', (csp.absence_notes IS NOT NULL AND TRIM(csp.absence_notes) <> ''),
           'tiene_nota_alumno', (csp.student_note IS NOT NULL AND TRIM(csp.student_note) <> ''),
           'creado', csp.created_at, 'actualizado', csp.updated_at) USING utf8mb4) COLLATE utf8mb4_unicode_ci
  FROM class_session_players csp CROSS JOIN cfg
  JOIN class_sessions cs ON cs.id = csp.session_id
  LEFT JOIN users ru ON ru.id = csp.bono_resolved_by

  UNION ALL
  SELECT _utf8mb4'alumnos' COLLATE utf8mb4_unicode_ci, CONVERT(JSON_OBJECT(
           'alumno', CONCAT('A-', LEFT(SHA2(CONCAT(cfg.sal, 'u', u.id), 256), 8)),
           'estado', u.status, 'alta_mes', DATE_FORMAT(u.created_at, '%Y-%m'),
           'categoria', pp.category, 'nivel', pp.level, 'posicion', pp.position,
           'edad_franja', IF(pp.birth_date IS NULL, NULL,
               CONCAT(FLOOR(TIMESTAMPDIFF(YEAR, pp.birth_date, CURDATE()) / 3) * 3, '-',
                      FLOOR(TIMESTAMPDIFF(YEAR, pp.birth_date, CURDATE()) / 3) * 3 + 2))) USING utf8mb4) COLLATE utf8mb4_unicode_ci
  FROM users u CROSS JOIN cfg
  LEFT JOIN player_profiles pp ON pp.player_id = u.id
  WHERE u.role = 'player'

  UNION ALL
  SELECT _utf8mb4'equipo' COLLATE utf8mb4_unicode_ci, CONVERT(JSON_OBJECT(
           'equipo', CONCAT('E-', LEFT(SHA2(CONCAT(cfg.sal, 'u', u.id), 256), 8)),
           'rol', u.role, 'estado', u.status, 'alta_mes', DATE_FORMAT(u.created_at, '%Y-%m')) USING utf8mb4) COLLATE utf8mb4_unicode_ci
  FROM users u CROSS JOIN cfg
  WHERE u.role <> 'player'

  UNION ALL
  SELECT _utf8mb4'locations' COLLATE utf8mb4_unicode_ci, CONVERT(JSON_OBJECT('id', l.id, 'nombre', l.name, 'tipo', l.type, 'activa', l.active) USING utf8mb4) COLLATE utf8mb4_unicode_ci
  FROM locations l

  UNION ALL
  SELECT _utf8mb4'academy_settings' COLLATE utf8mb4_unicode_ci, CONVERT(JSON_OBJECT('clave', s.setting_key, 'valor', s.setting_value) USING utf8mb4) COLLATE utf8mb4_unicode_ci
  FROM academy_settings s
  WHERE s.setting_key REGEXP '^(bono|academy_timezone|currency|moneda|precio|price|iva|tax|factur)'
    AND s.setting_key NOT REGEXP 'pass|secret|key|token|smtp'

  UNION ALL
  SELECT _utf8mb4'purchase_requests' COLLATE utf8mb4_unicode_ci, CONVERT(JSON_OBJECT(
           'id', pr.id, 'precio', pr.price, 'categoria', pr.category, 'prioridad', pr.priority,
           'estado', pr.status, 'pedido_por_rol', ru.role, 'creado', pr.created_at, 'revisado', pr.reviewed_at) USING utf8mb4) COLLATE utf8mb4_unicode_ci
  FROM purchase_requests pr
  LEFT JOIN users ru ON ru.id = pr.requested_by
)
SELECT f.tabla COLLATE utf8mb4_unicode_ci AS tabla,
       IF(f.fila LIKE '%@%', _utf8mb4'{"bloqueado":"la fila contenía @"}' COLLATE utf8mb4_unicode_ci, f.fila) AS fila
FROM filas f CROSS JOIN cfg
WHERE cfg.sal <> 'CAMBIA_ESTO' AND CHAR_LENGTH(cfg.sal) >= 20
UNION ALL
SELECT _utf8mb4'ERROR' COLLATE utf8mb4_unicode_ci, _utf8mb4'Cambia CAMBIA_ESTO (línea WITH) por 20 o más caracteres al azar y vuelve a ejecutar' COLLATE utf8mb4_unicode_ci
FROM cfg
WHERE cfg.sal = 'CAMBIA_ESTO' OR CHAR_LENGTH(cfg.sal) < 20;
