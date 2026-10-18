<?php

namespace App\Services;

/**
 * Usuarios que NO cuentan para las finanzas (cuentas de prueba, la del propio
 * administrador cuando prueba cosas…). Es el ÚNICO sitio de la política.
 *
 * Qué significa «excluido»:
 *  - su dinero (cargos, cobros, bonos, caducados, deuda) no entra en ninguna cifra;
 *  - toda CLASE en la que participe como alumno o como entrenador/responsable
 *    queda fuera de las cifras de Finanzas (servicio prestado, asistencia,
 *    análisis, entrenadores) y de la Revisión;
 *  - sigue existiendo y funcionando en la plataforma (clases, bonos, mensajes…):
 *    solo desaparece de Finanzas. Es reversible: al quitarlo vuelve a contar.
 *
 * No depende de la baja ni del estado del usuario: se elige a mano. La lista
 * va en `academy_settings.fin_excluded_user_ids` (ids separados por comas), sin
 * columnas nuevas; solo el superadmin raíz la edita (finance:master).
 *
 * Si la lista no se puede leer, no se excluye a nadie (las cifras salen como
 * antes): un error de lectura no debe romper Finanzas.
 *
 * `playerSql()`, `sessionSql()`, `filterRows()` y `parseIds()` son puras si se
 * les pasa la lista.
 */
class FinanceExclusion
{
    public const SETTING_KEY = 'fin_excluded_user_ids';

    /** @var int[]|null caché por petición */
    private static ?array $ids = null;

    /** @var int[]|null ids de sesión excluidas (caché por petición) */
    private static ?array $sessionIds = null;

    /** «3, 7,x,7» → [3, 7]. Pura. */
    public static function parseIds(?string $raw): array
    {
        $parts = array_filter(array_map('trim', explode(',', (string) $raw)), 'is_numeric');
        return self::clean(array_map('intval', $parts));
    }

    /** Ids excluidos (ordenados, sin repetir). */
    public static function ids(): array
    {
        if (self::$ids !== null) {
            return self::$ids;
        }
        try {
            $row = \Config\Database::connect()->table('academy_settings')
                ->select('setting_value')->where('setting_key', self::SETTING_KEY)->get()->getRowArray();
            return self::$ids = self::parseIds($row['setting_value'] ?? '');
        } catch (\Throwable $e) {
            log_message('error', 'FinanceExclusion::ids falló (no se excluye a nadie): ' . $e->getMessage());
            return self::$ids = [];
        }
    }

    public static function isExcluded(int $userId): bool
    {
        return in_array($userId, self::ids(), true);
    }

    /** Olvida la caché (tras guardar y en los tests). */
    public static function reset(): void
    {
        self::$ids        = null;
        self::$sessionIds = null;
    }

    /**
     * « AND col NOT IN (…)» para dejar fuera a los usuarios excluidos; cadena
     * vacía si no hay ninguno. Pura si se pasa $ids.
     *
     * @param int[]|null $ids
     */
    public static function playerSql(string $col, ?array $ids = null): string
    {
        $c = self::playerCond($col, $ids);
        return $c === '' ? '' : ' AND ' . $c;
    }

    /** Como playerSql() pero sin el « AND » (para ->where($cond, null, false)). */
    public static function playerCond(string $col, ?array $ids = null): string
    {
        $ids = self::clean($ids ?? self::ids());
        return $ids ? "{$col} NOT IN (" . implode(',', $ids) . ')' : '';
    }

    /**
     * « AND NOT EXISTS …» para dejar fuera las clases (alias de class_sessions
     * en $cs) donde participa un excluido como alumno o como entrenador. Pura
     * si se pasa $ids.
     *
     * @param int[]|null $ids
     */
    public static function sessionSql(string $cs = 'cs', ?array $ids = null): string
    {
        $c = self::sessionCond($cs, $ids);
        return $c === '' ? '' : ' AND ' . $c;
    }

    /** Como sessionSql() pero sin el « AND » (para ->where($cond, null, false)). */
    public static function sessionCond(string $cs = 'cs', ?array $ids = null): string
    {
        $ids = self::clean($ids ?? self::ids());
        if (!$ids) {
            return '';
        }
        $in = implode(',', $ids);
        return "NOT EXISTS (SELECT 1 FROM class_session_players fxp WHERE fxp.session_id = {$cs}.id"
             . " AND (fxp.user_id IN ({$in}) OR fxp.coach_id IN ({$in})))"
             . " AND NOT EXISTS (SELECT 1 FROM class_session_coaches fxc WHERE fxc.session_id = {$cs}.id"
             . " AND fxc.user_id IN ({$in}))";
    }

    /** Ids de las clases que quedan fuera por tener a un excluido. */
    public static function sessionIds(): array
    {
        if (self::$sessionIds !== null) {
            return self::$sessionIds;
        }
        $ids = self::ids();
        if (!$ids) {
            return self::$sessionIds = [];
        }
        try {
            $in  = implode(',', $ids);
            $res = \Config\Database::connect()->query(
                "SELECT session_id AS id FROM class_session_players WHERE user_id IN ({$in}) OR coach_id IN ({$in})
                 UNION SELECT session_id FROM class_session_coaches WHERE user_id IN ({$in})"
            )->getResultArray();
            return self::$sessionIds = array_map('intval', array_column($res, 'id'));
        } catch (\Throwable $e) {
            log_message('error', 'FinanceExclusion::sessionIds falló: ' . $e->getMessage());
            return self::$sessionIds = [];
        }
    }

    /**
     * Quita de una lista de filas las de usuarios excluidos y las de clases
     * excluidas. Pura si se pasan las listas.
     *
     * @param array<int,array> $rows
     * @param int[]|null $ids
     * @param int[]|null $sessionIds
     */
    public static function filterRows(array $rows, string $userKey = 'user_id', string $sessionKey = 'session_id',
                                      ?array $ids = null, ?array $sessionIds = null): array
    {
        $ids        = self::clean($ids ?? self::ids());
        $sessionIds = self::clean($sessionIds ?? ($ids ? self::sessionIds() : []));
        if (!$ids && !$sessionIds) {
            return $rows;
        }
        return array_values(array_filter($rows, static function ($r) use ($ids, $sessionIds, $userKey, $sessionKey) {
            if (isset($r[$userKey]) && in_array((int) $r[$userKey], $ids, true)) {
                return false;
            }
            return !(isset($r[$sessionKey]) && in_array((int) $r[$sessionKey], $sessionIds, true));
        }));
    }

    // ────────────────────────────────────────────────────────────────
    //  Gestión de la lista (solo el superadmin raíz)
    // ────────────────────────────────────────────────────────────────

    /** Usuarios excluidos hoy, con sus datos. */
    public function listExcluded(): array
    {
        $ids = self::ids();
        if (!$ids) {
            return [];
        }
        return \Config\Database::connect()->table('users')->select('id, name, email, role, status')
            ->whereIn('id', $ids)->orderBy('name')->get()->getResultArray();
    }

    /** Usuarios que se pueden añadir (todos los demás). */
    public function candidates(): array
    {
        $db   = \Config\Database::connect();
        $q    = $db->table('users')->select('id, name, email, role, status')->orderBy('name');
        $excl = self::ids();
        if ($excl) {
            $q->whereNotIn('id', $excl);
        }
        return $q->get()->getResultArray();
    }

    /**
     * Guarda la lista completa. Solo el raíz (lo exige el filtro y se vuelve a
     * comprobar aquí). Los ids que no existan se descartan. Queda en auditoría.
     *
     * @param  int[] $ids
     * @return array{success:bool,error?:string,ids?:int[]}
     */
    public function save(array $ids, int $actorId): array
    {
        if (!FinanceAccessService::isCurrentMaster() || (int) FinanceAccessService::current()['user']['id'] !== $actorId) {
            return ['success' => false, 'error' => 'Solo el administrador principal puede cambiar qué usuarios no cuentan en las finanzas.'];
        }
        $db  = \Config\Database::connect();
        $new = self::clean(array_map('intval', $ids));
        if ($new) {
            $exist = array_map('intval', array_column($db->table('users')->select('id')->whereIn('id', $new)->get()->getResultArray(), 'id'));
            $new   = self::clean($exist);
        }

        $old = self::ids();
        $now = date('Y-m-d H:i:s');
        $str = implode(',', $new);

        $db->transStart();
        $existing = $db->table('academy_settings')->select('id')->where('setting_key', self::SETTING_KEY)->get()->getRowArray();
        if ($existing) {
            $db->table('academy_settings')->where('id', $existing['id'])
                ->update(['setting_value' => $str, 'updated_at' => $now, 'updated_by' => $actorId]);
        } else {
            $db->table('academy_settings')->insert([
                'setting_key' => self::SETTING_KEY, 'setting_value' => $str, 'setting_type' => 'string',
                'updated_at' => $now, 'updated_by' => $actorId,
            ]);
        }
        $db->transComplete();
        if (!$db->transStatus()) {
            return ['success' => false, 'error' => 'No se pudo guardar. Inténtalo de nuevo.'];
        }

        if ($old !== $new) {
            AuditService::record(
                'finance_exclusion', null, AuditService::UPDATE,
                ['excluded_user_ids' => $old], ['excluded_user_ids' => $new],
                'Cambio de los usuarios que no cuentan en las finanzas', $actorId
            );
        }
        self::reset();
        return ['success' => true, 'ids' => $new];
    }

    /** Enteros positivos, sin repetir y ordenados. @param int[] $ids */
    private static function clean(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($i) => $i > 0)));
        sort($ids);
        return $ids;
    }
}
