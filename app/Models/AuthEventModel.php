<?php

namespace App\Models;

use CodeIgniter\Model;

class AuthEventModel extends Model
{
    protected $table         = 'auth_events';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = false;
    protected $allowedFields  = [
        'event_type',
        'identifier',
        'user_id',
        'ip_address',
        'user_agent',
        'meta',
        'created_at',
    ];

    // Tipos de evento conocidos (documentación / validación ligera).
    public const TYPES = [
        'login_success', 'login_fail', 'logout', 'lockout',
        'pwreset_request', 'pwreset_success', 'pwreset_fail',
        'pwchange_success', 'pwchange_fail', 'admin_pwreset',
    ];

    /**
     * Cuenta eventos de un tipo para un identificador dentro de los
     * últimos $minutes minutos, opcionalmente solo los posteriores a
     * una fecha (p. ej. el último login correcto).
     */
    public function countRecent(string $eventType, string $column, string $value, int $minutes, ?string $since = null): int
    {
        $q = $this->where('event_type', $eventType)
            ->where($column, $value)
            ->where('created_at >=', date('Y-m-d H:i:s', time() - $minutes * 60));

        if ($since !== null) {
            $q->where('created_at >', $since);
        }

        return $q->countAllResults();
    }

    /**
     * Fecha del evento más reciente de un tipo para un identificador,
     * o null si no hay ninguno.
     */
    public function lastAt(string $eventType, string $column, string $value): ?string
    {
        $row = $this->select('created_at')
            ->where('event_type', $eventType)
            ->where($column, $value)
            ->orderBy('created_at', 'DESC')
            ->first();

        return $row['created_at'] ?? null;
    }

    /**
     * Últimos eventos "interesantes" para el panel de seguridad.
     */
    public function recentForPanel(int $limit = 30): array
    {
        return $this->select('auth_events.*, u.name AS user_name, u.role AS user_role')
            ->join('users u', 'u.id = auth_events.user_id', 'left')
            ->whereIn('event_type', [
                'login_fail', 'lockout', 'pwreset_request',
                'pwreset_success', 'pwchange_success', 'admin_pwreset',
            ])
            ->orderBy('auth_events.created_at', 'DESC')
            ->limit($limit)
            ->find();
    }
}
