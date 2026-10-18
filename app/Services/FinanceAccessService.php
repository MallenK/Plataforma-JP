<?php

namespace App\Services;

/**
 * Quién puede ver qué dentro de Finanzas — UN solo sitio con la política.
 *
 * Tres niveles:
 *   full   → todo Finanzas (resumen, cobros, gastos, análisis, historial de alumnos…).
 *            Solo el superadmin «raíz» (id 2 / su email) y las personas a las que ÉL
 *            da acceso expreso desde Finanzas › Configuración.
 *   review → solo «Revisión de clases»: saber si se dio la clase, si se pasó lista,
 *            si se cerró, si tiene entrenador… sin ningún importe. Es lo que tienen
 *            el resto de admin y superadmin.
 *   none   → cualquier otro rol, usuario de baja o sesión que ya no es válida.
 *
 * Reglas de seguridad:
 *   - Falla CERRADO: ante cualquier error (BD, ajuste ilegible) se queda en el
 *     nivel más bajo, nunca en el más alto.
 *   - El nivel se calcula con la fila ACTUAL del usuario en BD, no con lo que
 *     quedó en la sesión: si le quitan el rol o lo dan de baja, pierde el acceso
 *     en la siguiente petición, sin esperar a que caduque la sesión.
 *   - La lista de accesos se guarda como ids en `academy_settings` y solo cuenta
 *     para usuarios ACTIVOS con rol admin/superadmin.
 *   - Solo el superadmin raíz puede cambiar la lista (`isMaster`).
 *
 * `levelFor()`, `parseIds()` e `isMaster()` son puras (sin BD) para poder probarlas.
 */
class FinanceAccessService
{
    public const LEVEL_NONE   = 'none';
    public const LEVEL_REVIEW = 'review';
    public const LEVEL_FULL   = 'full';

    /** Superadmin raíz de la plataforma (mismo criterio que BaseController::isProtectedUser). */
    public const MASTER_ID    = 2;
    public const MASTER_EMAIL = 'sergimallenweb@gmail.com';

    public const SETTING_KEY = 'fin_full_access_user_ids';

    /** Roles que pueden llegar a entrar en Finanzas (con el nivel que toque). */
    public const ELIGIBLE_ROLES = ['admin', 'superadmin'];

    /** @var array{level:string,user:?array,master:bool}|null caché por petición */
    private static ?array $current = null;
    /** @var int[]|null */
    private static ?array $granted = null;

    // ────────────────────────────────────────────────────────────────
    //  Reglas puras
    // ────────────────────────────────────────────────────────────────

    /** «1, 5;7  x» → [1, 5, 7]. Ids positivos, sin repetir, en orden. */
    public static function parseIds(?string $raw): array
    {
        $ids = [];
        foreach (preg_split('/[^0-9]+/', (string) $raw) ?: [] as $part) {
            if ($part !== '' && (int) $part > 0) {
                $ids[(int) $part] = true;
            }
        }
        return array_keys($ids);
    }

    /**
     * ¿Es administrador principal? Todo `superadmin` lo es (los ids y emails
     * cambian entre entornos: prod, PPR, local), además del id/email raíz.
     */
    public static function isMaster(?array $user): bool
    {
        if (!$user) {
            return false;
        }
        return ($user['role'] ?? '') === 'superadmin'
            || (int) ($user['id'] ?? 0) === self::MASTER_ID
            || strtolower(trim((string) ($user['email'] ?? ''))) === self::MASTER_EMAIL;
    }

    /**
     * Nivel de acceso de un usuario (fila de `users`) dada la lista de ids con acceso total.
     *
     * @param int[] $grantedIds
     */
    public static function levelFor(?array $user, array $grantedIds): string
    {
        if (!$user) {
            return self::LEVEL_NONE;
        }
        if (($user['status'] ?? 'active') !== 'active') {
            return self::LEVEL_NONE;
        }
        if (!in_array($user['role'] ?? '', self::ELIGIBLE_ROLES, true)) {
            return self::LEVEL_NONE;
        }
        if (self::isMaster($user) || in_array((int) ($user['id'] ?? 0), $grantedIds, true)) {
            return self::LEVEL_FULL;
        }
        return self::LEVEL_REVIEW;
    }

    /** ¿`$level` alcanza lo que pide `$required` ('full' | 'review')? */
    public static function satisfies(string $level, string $required): bool
    {
        return match ($required) {
            self::LEVEL_FULL   => $level === self::LEVEL_FULL,
            self::LEVEL_REVIEW => $level === self::LEVEL_FULL || $level === self::LEVEL_REVIEW,
            default            => false,
        };
    }

    // ────────────────────────────────────────────────────────────────
    //  Usuario en sesión (con caché por petición)
    // ────────────────────────────────────────────────────────────────

    /** @return array{level:string,user:?array,master:bool} */
    public static function current(): array
    {
        if (self::$current !== null) {
            return self::$current;
        }
        $deny = ['level' => self::LEVEL_NONE, 'user' => null, 'master' => false];

        try {
            $id = (int) session('id');
            if ($id <= 0 || !session('isLoggedIn')) {
                return self::$current = $deny;
            }
            $user = \Config\Database::connect()->table('users')
                ->select('id, name, email, role, status')->where('id', $id)->get()->getRowArray();
            if (!$user) {
                return self::$current = $deny;
            }
            return self::$current = [
                'level'  => self::levelFor($user, self::grantedIds()),
                'user'   => $user,
                'master' => self::isMaster($user) && in_array($user['role'], self::ELIGIBLE_ROLES, true) && $user['status'] === 'active',
            ];
        } catch (\Throwable $e) {
            log_message('error', 'FinanceAccessService::current falló (se deniega): ' . $e->getMessage());
            return self::$current = $deny;
        }
    }

    public static function level(): string
    {
        return self::current()['level'];
    }

    public static function canFull(): bool
    {
        return self::current()['level'] === self::LEVEL_FULL;
    }

    public static function canReview(): bool
    {
        return self::satisfies(self::current()['level'], self::LEVEL_REVIEW);
    }

    public static function isCurrentMaster(): bool
    {
        return self::current()['master'];
    }

    /** Olvida lo cacheado (tests, o tras cambiar la lista en esta misma petición). */
    public static function reset(): void
    {
        self::$current = null;
        self::$granted = null;
    }

    // ────────────────────────────────────────────────────────────────
    //  Lista de accesos
    // ────────────────────────────────────────────────────────────────

    /** Ids con acceso total además del raíz. Si el ajuste no se puede leer: lista vacía (cerrado). */
    public static function grantedIds(): array
    {
        if (self::$granted !== null) {
            return self::$granted;
        }
        try {
            $row = \Config\Database::connect()->table('academy_settings')
                ->select('setting_value')->where('setting_key', self::SETTING_KEY)->get()->getRowArray();
            return self::$granted = self::parseIds($row['setting_value'] ?? '');
        } catch (\Throwable $e) {
            log_message('error', 'FinanceAccessService::grantedIds falló (se deniega): ' . $e->getMessage());
            return self::$granted = [];
        }
    }

    /**
     * Personas que pueden recibir acceso total: admin y superadmin activos
     * (sin el raíz, que siempre lo tiene). Cada fila trae `granted` (bool).
     */
    public function candidates(): array
    {
        $granted = self::grantedIds();
        $rows = \Config\Database::connect()->table('users')
            ->select('id, name, email, role')
            ->whereIn('role', self::ELIGIBLE_ROLES)->where('status', 'active')
            ->orderBy('role', 'DESC')->orderBy('name')->get()->getResultArray();

        $out = [];
        foreach ($rows as $r) {
            if (self::isMaster($r)) {
                continue;
            }
            $r['granted'] = in_array((int) $r['id'], $granted, true);
            $out[] = $r;
        }
        return $out;
    }

    /**
     * Guarda la lista (solo el raíz; lo comprueba el filtro Y se vuelve a comprobar aquí).
     * Los ids que no sean admin/superadmin activos se descartan.
     *
     * @param  int[] $ids
     * @return array{success:bool,error?:string,ids?:int[]}
     */
    public function saveGranted(array $ids, int $actorId): array
    {
        if (!self::isCurrentMaster() || (int) self::current()['user']['id'] !== $actorId) {
            return ['success' => false, 'error' => 'Solo el administrador principal puede cambiar quién ve las finanzas.'];
        }
        $db = \Config\Database::connect();

        $valid = [];
        foreach ($this->candidates() as $c) {
            $valid[(int) $c['id']] = true;
        }
        $new = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($i) => isset($valid[$i]))));
        sort($new);

        $old = self::grantedIds();
        $str = implode(',', $new);
        $now = date('Y-m-d H:i:s');

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

        sort($old);
        if ($old !== $new) {
            AuditService::record(
                'finance_access', null, AuditService::UPDATE,
                ['full_access_user_ids' => $old], ['full_access_user_ids' => $new],
                'Cambio de quién ve todas las finanzas', $actorId
            );
        }
        self::reset();
        return ['success' => true, 'ids' => $new];
    }
}
