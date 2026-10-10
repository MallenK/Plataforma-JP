<?php

namespace App\Services;

/**
 * Registro de auditoría (`audit_log`) — «Nada se borra» (v1.33.0).
 *
 * Solo se añaden filas: quién hizo qué, cuándo, qué había antes, qué quedó
 * después y por qué. El código nunca actualiza ni borra filas de esta tabla.
 * Igual que el libro de bonos, registrar NUNCA tumba la operación de negocio:
 * si la tabla no existe o falla el insert, se anota en el log y se sigue.
 *
 * `changes()` y `sanitize()` son puras (sin BD) para poder probarlas.
 */
class AuditService
{
    public const CREATE  = 'create';
    public const UPDATE  = 'update';
    public const VOID    = 'void';      // anulado (sigue existiendo, marcado)
    public const ARCHIVE = 'archive';   // archivado / dado de baja
    public const RESTORE = 'restore';
    public const DELETE  = 'delete';    // borrado permitido (sin histórico) — se guarda la foto
    public const BLOCKED = 'blocked';   // intento de borrado rechazado por tener histórico
    public const VIEW    = 'view';      // consulta de datos personales (p. ej. historial completo de un alumno)

    /** Campos que nunca se guardan en la auditoría. */
    private const SECRET_KEYS = ['password', 'password_hash', 'token', 'remember_token'];

    private const LABELS = [
        self::CREATE  => 'Alta',
        self::UPDATE  => 'Cambio',
        self::VOID    => 'Anulación',
        self::ARCHIVE => 'Archivado',
        self::RESTORE => 'Restaurado',
        self::DELETE  => 'Borrado',
        self::BLOCKED => 'Borrado bloqueado',
        self::VIEW    => 'Consulta',
    ];

    public static function label(string $action): string
    {
        return self::LABELS[$action] ?? $action;
    }

    /** Quita campos secretos (contraseñas, tokens) de una fila. */
    public static function sanitize(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }
        foreach (self::SECRET_KEYS as $k) {
            unset($row[$k]);
        }
        return $row;
    }

    /**
     * Solo los campos que cambian entre dos versiones de una fila.
     * Compara como texto (la BD devuelve strings; el formulario, a veces números).
     *
     * @return array{0:array,1:array} [antes, después] con las claves cambiadas
     */
    public static function changes(array $before, array $after): array
    {
        $b = [];
        $a = [];
        foreach ($after as $k => $v) {
            $old = $before[$k] ?? null;
            if ((string) ($old ?? "\0null") !== (string) ($v ?? "\0null")) {
                $b[$k] = $old;
                $a[$k] = $v;
            }
        }
        return [$b, $a];
    }

    /**
     * Anota una acción. Nunca lanza.
     *
     * @param int|null $actorId null = usuario de la sesión actual
     */
    public static function record(
        string $entityType,
        ?int $entityId,
        string $action,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null,
        ?int $actorId = null
    ): void {
        try {
            $actorRole = null;
            try {
                $sessionId = session('id') ? (int) session('id') : null;
                if ($actorId === null) {
                    $actorId = $sessionId;
                }
                if ($actorId !== null && $actorId === $sessionId) {
                    $actorRole = session('role') ?: null;
                }
            } catch (\Throwable $e) {
                // CLI / sin sesión: el rol queda vacío
            }
            $ip = null;
            try {
                if (!is_cli()) {
                    $ip = service('request')->getIPAddress();
                }
            } catch (\Throwable $e) {
                $ip = null;
            }

            $json = static fn(?array $r) => $r === null ? null
                : json_encode(self::sanitize($r), JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

            \Config\Database::connect()->table('audit_log')->insert([
                'entity_type' => mb_substr($entityType, 0, 40),
                'entity_id'   => $entityId,
                'action'      => mb_substr($action, 0, 20),
                'before_json' => $json($before),
                'after_json'  => $json($after),
                'reason'      => $reason !== null && trim($reason) !== '' ? mb_substr(trim($reason), 0, 500) : null,
                'actor_id'    => $actorId,
                'actor_role'  => $actorRole,
                'ip_address'  => $ip,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'AuditService::record falló (' . $entityType . ' ' . $action . '): ' . $e->getMessage());
        }
    }

    /**
     * Historial de una entidad, del más reciente al más antiguo.
     *
     * @return array<int,array>
     */
    public function forEntity(string $entityType, int $entityId, int $limit = 100): array
    {
        try {
            return \Config\Database::connect()->table('audit_log a')
                ->select('a.*, u.name AS actor_name')
                ->join('users u', 'u.id = a.actor_id', 'left')
                ->where('a.entity_type', $entityType)
                ->where('a.entity_id', $entityId)
                ->orderBy('a.id', 'DESC')
                ->limit($limit)
                ->get()->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', 'AuditService::forEntity falló: ' . $e->getMessage());
            return [];
        }
    }
}
