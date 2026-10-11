<?php

namespace App\Services;

/**
 * Alumnos de prueba creados antes del lanzamiento («Alumno Prueba», «Prueba»,
 * «Rtrt»…). Sus registros no son actividad real: ensucian Finanzas, el
 * calendario y los informes, así que se eliminan POR COMPLETO (excepción
 * explícita a «Nada se borra», pedida por la dirección el 2026-10-10).
 *
 * Qué se borra de cada alumno de prueba:
 *  - las clases en las que SOLO había alumnos de prueba (con sus
 *    entrenadores y adjuntos); de las clases con alumnos reales solo se le
 *    quita a él;
 *  - sus bonos, el libro de movimientos, cargos, cobros y repartos;
 *  - ficha, anotaciones, mensajes y conversaciones, tickets, avisos,
 *    preferencias, suscripciones push, accesos, permisos de carpetas…;
 *  - el propio usuario.
 * `audit_log` se conserva (solo se añade una fila por usuario, sin datos
 * personales). Los ficheros subidos quedan huérfanos en disco (no se tocan).
 *
 * `isTestName()` es pura.
 */
class TestDataService
{
    /** Palabras que forman el nombre de un alumno de prueba. */
    public const TEST_WORDS = ['prueba', 'pruebas', 'rtrt'];
    /** Palabras que pueden acompañarlas sin que deje de ser un nombre de prueba. */
    public const FILLER_WORDS = ['alumno', 'alumna'];

    private \CodeIgniter\Database\BaseConnection $db;

    public function __construct(?\CodeIgniter\Database\BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * ¿Es un nombre de alumno de prueba? Todas sus palabras deben ser de
     * prueba o de relleno, y al menos una de prueba: «Alumno Prueba»,
     * «Prueba», «Rtrt», «prueba prueba» sí; «Pruebas García», «Alumno» no.
     * Pura.
     */
    public static function isTestName(?string $name): bool
    {
        $name  = mb_strtolower(trim((string) $name), 'UTF-8');
        $words = preg_split('/[\s._\-]+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($words === []) {
            return false;
        }
        $hasTest = false;
        foreach ($words as $w) {
            if (in_array($w, self::TEST_WORDS, true)) {
                $hasTest = true;
            } elseif (!in_array($w, self::FILLER_WORDS, true)) {
                return false;
            }
        }
        return $hasTest;
    }

    /** Alumnos (rol player) cuyo nombre es de prueba. */
    public function findTestPlayers(): array
    {
        $rows = $this->db->table('users')->select('id, name, email, status, created_at')
            ->where('role', 'player')->orderBy('name')->get()->getResultArray();
        return array_values(array_filter($rows, static fn($u) => self::isTestName($u['name'])));
    }

    /**
     * Qué se borraría (etiqueta => nº de filas) y qué clases desaparecen.
     *
     * @param int[] $ids
     * @return array{users:array,counts:array<string,int>,sessions:int[],series:int[],blockers:string[]}
     */
    public function plan(array $ids): array
    {
        $ids = $this->onlyTestPlayers($ids);
        $out = ['users' => [], 'counts' => [], 'sessions' => [], 'series' => [], 'blockers' => []];
        if ($ids === []) {
            return $out;
        }
        $out['users']    = $this->db->table('users')->select('id, name, email, status')->whereIn('id', $ids)->get()->getResultArray();
        $out['sessions'] = $this->testOnlySessions($ids);
        $out['series']   = $this->testOnlySeries($out['sessions']);

        foreach ($this->targets($ids, $out['sessions'], $out['series']) as $label => [$table, $where]) {
            if (!$this->db->tableExists($table)) {
                continue;
            }
            $n = (int) $this->db->table($table)->where($where, null, false)->countAllResults();
            if ($n > 0) {
                $out['counts'][$label] = $n;
            }
        }

        // Un descuento de un alumno REAL hecho con un bono de prueba no se
        // puede borrar sin descuadrar su historial: se para y se avisa.
        $bonoIds = $this->bonoIds($ids);
        if ($bonoIds) {
            $n = (int) $this->db->table('class_session_players')
                ->whereIn('bono_deducted_from_id', $bonoIds)->whereNotIn('user_id', $ids)->countAllResults();
            if ($n > 0) {
                $out['blockers'][] = "{$n} descuento(s) de alumnos reales están hechos con un bono de un alumno de prueba.";
            }
        }
        return $out;
    }

    /**
     * Borra todo lo de esos alumnos de prueba. Solo acepta usuarios con rol
     * player y nombre de prueba (aunque se pasen otros ids).
     *
     * @param int[] $ids
     * @return array{success:bool,error?:string,users?:int,counts?:array<string,int>,sessions?:int}
     */
    public function purge(array $ids, ?int $actorId = null): array
    {
        $plan = $this->plan($ids);
        if ($plan['users'] === []) {
            return ['success' => false, 'error' => 'No hay alumnos de prueba que eliminar.'];
        }
        if ($plan['blockers']) {
            return ['success' => false, 'error' => implode(' ', $plan['blockers'])];
        }
        $ids      = array_map('intval', array_column($plan['users'], 'id'));
        $sessions = $plan['sessions'];
        $series   = $plan['series'];

        $this->db->transBegin();
        try {
            // Hijos antes que padres: repartos → cargos/cobros, mensajes → conversaciones…
            foreach ($this->targets($ids, $sessions, $series) as [$table, $where]) {
                if ($this->db->tableExists($table)) {
                    $this->db->table($table)->where($where, null, false)->delete();
                }
            }
            if ($this->db->transStatus() === false) {
                throw new \RuntimeException('La base de datos rechazó el borrado.');
            }
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'TestDataService::purge: ' . $e->getMessage());
            return ['success' => false, 'error' => 'No se ha borrado nada: ' . $e->getMessage()];
        }

        foreach ($plan['users'] as $u) {
            // Sin datos personales: solo el id y qué se borró.
            AuditService::record('user', (int) $u['id'], AuditService::DELETE, ['id' => (int) $u['id'], 'role' => 'player'], null,
                'Alumno de prueba eliminado con todos sus registros (' . count($sessions) . ' clases de prueba)', $actorId);
        }
        return ['success' => true, 'users' => count($ids), 'counts' => $plan['counts'], 'sessions' => count($sessions)];
    }

    // ────────────────────────────────────────────────────────────────

    /** Filtra a usuarios que de verdad son alumnos de prueba. */
    private function onlyTestPlayers(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }
        $rows = $this->db->table('users')->select('id, name')->whereIn('id', $ids)->where('role', 'player')->get()->getResultArray();
        return array_map('intval', array_column(array_filter($rows, static fn($u) => self::isTestName($u['name'])), 'id'));
    }

    /** Clases cuyos alumnos son TODOS de prueba. */
    private function testOnlySessions(array $ids): array
    {
        $in = implode(',', $ids);
        return array_map('intval', array_column($this->db->query(
            "SELECT p.session_id AS id FROM class_session_players p
             GROUP BY p.session_id
             HAVING SUM(p.user_id NOT IN ({$in})) = 0 AND SUM(p.user_id IN ({$in})) > 0"
        )->getResultArray(), 'id'));
    }

    /**
     * Series (plantillas) cuyas clases son todas de prueba. Se conserva la que
     * forme parte de una cadena de renovaciones con otra serie.
     */
    private function testOnlySeries(array $sessions): array
    {
        if ($sessions === []) {
            return [];
        }
        $s = implode(',', array_map('intval', $sessions));
        return array_map('intval', array_column($this->db->query(
            "SELECT DISTINCT c.id FROM classes c
             JOIN class_sessions cs ON cs.class_id = c.id AND cs.id IN ({$s})
             WHERE NOT EXISTS (SELECT 1 FROM class_sessions o WHERE o.class_id = c.id AND o.id NOT IN ({$s}))
               AND c.renewed_to_class_id IS NULL AND c.renewed_from_class_id IS NULL
               AND NOT EXISTS (SELECT 1 FROM classes r WHERE r.renewed_to_class_id = c.id OR r.renewed_from_class_id = c.id)"
        )->getResultArray(), 'id'));
    }

    private function bonoIds(array $ids): array
    {
        return array_map('intval', array_column($this->db->table('player_bonos')->select('id')
            ->whereIn('player_id', $ids)->get()->getResultArray(), 'id'));
    }

    /**
     * Tablas y condiciones a borrar, en orden (hijos primero).
     *
     * @return array<string,array{0:string,1:string}> etiqueta => [tabla, WHERE]
     */
    private function targets(array $ids, array $sessions, array $series): array
    {
        $u  = implode(',', array_map('intval', $ids));
        $s  = $sessions ? implode(',', array_map('intval', $sessions)) : '0';
        $c  = $series ? implode(',', array_map('intval', $series)) : '0';
        $bonos    = "SELECT id FROM player_bonos WHERE player_id IN ({$u})";
        $charges  = "SELECT id FROM fin_charges WHERE player_id IN ({$u}) OR bono_id IN ({$bonos})";
        $payments = "SELECT id FROM fin_payments WHERE player_id IN ({$u})";
        $convs    = "SELECT id FROM conversations WHERE user1_id IN ({$u}) OR user2_id IN ({$u})";
        $tickets  = "SELECT id FROM tickets WHERE user_id IN ({$u})";
        $notifs   = "SELECT id FROM notifications WHERE sender_id IN ({$u})";

        // MySQL no deja usar en un subquery la misma tabla que se borra:
        // las subconsultas de la propia tabla van envueltas en una derivada.
        $wrap = static fn(string $sql) => "SELECT id FROM ({$sql}) AS t";

        return [
            'repartos de cobros'            => ['fin_payment_allocations', "charge_id IN ({$charges}) OR payment_id IN ({$payments})"],
            'cargos'                        => ['fin_charges', "id IN (" . $wrap($charges) . ")"],
            'cobros'                        => ['fin_payments', "player_id IN ({$u})"],
            'movimientos de bono'           => ['bono_movements', "player_id IN ({$u}) OR bono_id IN ({$bonos})"],
            // `class_session_attachments.player_id` es la fila de asistencia, no el usuario.
            'adjuntos de clases'            => ['class_session_attachments', "session_id IN ({$s})
                                                    OR player_id IN (SELECT id FROM class_session_players WHERE user_id IN ({$u}))"],
            'asistencias a clases'          => ['class_session_players', "user_id IN ({$u}) OR session_id IN ({$s})"],
            'entrenadores de clases de prueba' => ['class_session_coaches', "session_id IN ({$s})"],
            'clases de prueba'              => ['class_sessions', "id IN ({$s})"],
            'series de clases de prueba'    => ['classes', "id IN ({$c})"],
            'bonos'                         => ['player_bonos', "player_id IN ({$u})"],
            'mensajes'                      => ['messages', "sender_id IN ({$u}) OR conversation_id IN ({$convs})"],
            'conversaciones'                => ['conversations', "user1_id IN ({$u}) OR user2_id IN ({$u})"],
            'adjuntos de tickets'           => ['ticket_attachments', "ticket_id IN ({$tickets})
                                                    OR reply_id IN (SELECT id FROM ticket_replies WHERE user_id IN ({$u}))"],
            'historial de tickets'          => ['ticket_events', "ticket_id IN ({$tickets})"],
            'cambios de estado de tickets'  => ['ticket_status_log', "ticket_id IN ({$tickets})"],
            'respuestas de tickets'         => ['ticket_replies', "user_id IN ({$u}) OR ticket_id IN ({$tickets})"],
            'tickets'                       => ['tickets', "user_id IN ({$u})"],
            'avisos recibidos'              => ['notification_recipients', "recipient_id IN ({$u}) OR notification_id IN ({$notifs})"],
            'avisos enviados'               => ['notifications', "sender_id IN ({$u})"],
            'preferencias de avisos'        => ['notification_preferences', "user_id IN ({$u})"],
            'suscripciones push'            => ['push_subscriptions', "user_id IN ({$u})"],
            'ficha del alumno'              => ['player_profiles', "player_id IN ({$u})"],
            'anotaciones'                   => ['player_annotations', "player_id IN ({$u})"],
            'observaciones'                 => ['observations', "player_id IN ({$u})"],
            'métricas'                      => ['player_metrics', "player_id IN ({$u})"],
            'planes'                        => ['player_plans', "player_id IN ({$u})"],
            'asistencias antiguas'          => ['session_attendance', "player_id IN ({$u})"],
            'permisos de carpetas'          => ['folder_permissions', "user_id IN ({$u})"],
            'participación en eventos'      => ['event_participants', "user_id IN ({$u})"],
            'equipos de eventos'            => ['event_team_members', "user_id IN ({$u})"],
            'recuperación de contraseña'    => ['password_resets', "user_id IN ({$u})"],
            'accesos (login)'               => ['auth_events', "user_id IN ({$u})"],
            'correos enviados'              => ['email_log', "recipient_id IN ({$u})"],
            'registros'                     => ['logs', "user_id IN ({$u})"],
            'usuario'                       => ['users', "id IN ({$u})"],
        ];
    }
}
