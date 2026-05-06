<?php

namespace App\Models;

use CodeIgniter\Model;

class PlayerBonoModel extends Model
{
    protected $table            = 'player_bonos';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps    = true;
    protected $dateFormat       = 'datetime';
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';

    protected $allowedFields = [
        'player_id',
        'bono_type_id',
        'sessions_total',
        'sessions_remaining',
        'start_date',
        'expires_at',
        'notes',
        'created_by',
    ];

    protected $validationRules = [
        'player_id'    => 'permit_empty|is_natural_no_zero',
        'bono_type_id' => 'required|is_natural_no_zero',
        'start_date'   => 'required|valid_date',
    ];

    // ────────────────────────────────────────────────────────────────
    //  Consultas específicas
    // ────────────────────────────────────────────────────────────────

    /**
     * Bono activo de un jugador: el bono MÁS ANTIGUO con sesiones
     * restantes > 0 y no caducado. La cola es FIFO — los bonos más
     * recientes esperan a que el actual se agote o caduque.
     */
    public function getActiveBono(int $playerId): ?array
    {
        $today = date('Y-m-d');

        return $this->where('player_id', $playerId)
            ->where('sessions_remaining >', 0)
            ->groupStart()
                ->where('expires_at IS NULL')
                ->orWhere('expires_at >=', $today)
            ->groupEnd()
            ->orderBy('created_at', 'ASC')
            ->first();
    }

    /**
     * Comprueba si un jugador tiene algún bono activo.
     */
    public function hasActiveBono(int $playerId): bool
    {
        return $this->getActiveBono($playerId) !== null;
    }

    /**
     * Bonos en cola de un jugador: tienen sesiones restantes y no han
     * caducado, pero NO son el activo (el activo es el más antiguo).
     * Devuelve cero o más bonos ordenados por fecha de creación
     * (próximo a activarse primero).
     */
    public function getQueuedBonos(int $playerId): array
    {
        $today  = date('Y-m-d');
        $active = $this->getActiveBono($playerId);
        if (!$active) {
            return [];
        }

        return $this->where('player_id', $playerId)
            ->where('id !=', (int)$active['id'])
            ->where('sessions_remaining >', 0)
            ->groupStart()
                ->where('expires_at IS NULL')
                ->orWhere('expires_at >=', $today)
            ->groupEnd()
            ->orderBy('created_at', 'ASC')
            ->findAll();
    }

    /**
     * Descuenta 1 sesión del bono activo de un jugador.
     * Retorna true si se descontó, false si no hay bono activo.
     */
    public function deductSession(int $playerId): bool
    {
        return $this->deductSessionDetailed($playerId) !== null;
    }

    /**
     * Versión detallada de deductSession: descuenta 1 sesión y devuelve
     * el bono resultante (con `sessions_remaining` actualizado y la
     * info necesaria para emitir notificaciones).
     *
     * @return array|null  null si no había bono activo
     */
    public function deductSessionDetailed(int $playerId): ?array
    {
        $bono = $this->getActiveBono($playerId);
        if (!$bono) {
            return null;
        }

        $newRemaining = max(0, (int)$bono['sessions_remaining'] - 1);
        $this->update($bono['id'], ['sessions_remaining' => $newRemaining]);

        $bono['sessions_remaining_before'] = (int)$bono['sessions_remaining'];
        $bono['sessions_remaining']        = $newRemaining;
        return $bono;
    }

    /**
     * Lista todos los bonos con datos del jugador y tipo de bono.
     * LEFT JOIN para incluir bonos sin jugador asignado.
     */
    public function getAllWithDetails(): array
    {
        return $this->db->table('player_bonos pb')
            ->select('pb.*, u.name AS player_name, u.email AS player_email, u.avatar AS player_avatar, bt.name AS bono_name, bt.sessions AS bono_sessions_original')
            ->join('users u',       'u.id = pb.player_id', 'left')
            ->join('bono_types bt', 'bt.id = pb.bono_type_id')
            ->orderBy('pb.created_at', 'DESC')
            ->get()->getResultArray();
    }

    /**
     * Bonos activos con detalles (incluye bonos sin jugador asignado).
     */
    public function getActiveBonosWithDetails(): array
    {
        $today = date('Y-m-d');

        return $this->db->table('player_bonos pb')
            ->select('pb.*, u.name AS player_name, u.email AS player_email, u.avatar AS player_avatar, bt.name AS bono_name, bt.sessions AS bono_sessions_original')
            ->join('users u',       'u.id = pb.player_id', 'left')
            ->join('bono_types bt', 'bt.id = pb.bono_type_id')
            ->where('pb.sessions_remaining >', 0)
            ->groupStart()
                ->where('pb.expires_at IS NULL')
                ->orWhere('pb.expires_at >=', $today)
            ->groupEnd()
            ->orderBy('pb.created_at', 'DESC')
            ->get()->getResultArray();
    }

    /**
     * Historial completo de bonos de un jugador concreto.
     */
    public function getBonosForPlayer(int $playerId): array
    {
        return $this->db->table('player_bonos pb')
            ->select('pb.*, bt.name AS bono_name, bt.sessions AS bono_sessions_original, u2.name AS created_by_name')
            ->join('bono_types bt', 'bt.id = pb.bono_type_id')
            ->join('users u2',      'u2.id = pb.created_by', 'left')
            ->where('pb.player_id', $playerId)
            ->orderBy('pb.created_at', 'DESC')
            ->get()->getResultArray();
    }

    /**
     * Estadísticas de resumen para el dashboard de bonos.
     */
    public function getStats(): array
    {
        $today     = date('Y-m-d');
        $thisMonth = date('Y-m-01');

        $active = (int)$this->db->table('player_bonos')
            ->where('sessions_remaining >', 0)
            ->groupStart()
                ->where('expires_at IS NULL')
                ->orWhere('expires_at >=', $today)
            ->groupEnd()
            ->countAllResults();

        $issuedThisMonth = (int)$this->db->table('player_bonos')
            ->where('start_date >=', $thisMonth)
            ->countAllResults();

        $expiringSoon = (int)$this->db->table('player_bonos')
            ->where('sessions_remaining >', 0)
            ->where('expires_at >=', $today)
            ->where('expires_at <=', date('Y-m-d', strtotime('+7 days')))
            ->countAllResults();

        $unassigned = (int)$this->db->table('player_bonos')
            ->where('player_id IS NULL')
            ->where('sessions_remaining >', 0)
            ->countAllResults();

        // Bonos asignados con 0 sesiones (agotados pero no vencidos en fecha,
        // o ya vencidos): el alumno necesita renovar.
        $depleted = (int)$this->db->table('player_bonos')
            ->where('player_id IS NOT NULL')
            ->where('sessions_remaining', 0)
            ->countAllResults();

        // Bonos asignados con exactamente 1 sesión restante (alerta).
        $lowSessions = (int)$this->db->table('player_bonos')
            ->where('player_id IS NOT NULL')
            ->where('sessions_remaining', 1)
            ->groupStart()
                ->where('expires_at IS NULL')
                ->orWhere('expires_at >=', $today)
            ->groupEnd()
            ->countAllResults();

        return [
            'active'            => $active,
            'issued_this_month' => $issuedThisMonth,
            'expiring_soon'     => $expiringSoon,
            'unassigned'        => $unassigned,
            'depleted'          => $depleted,
            'low_sessions'      => $lowSessions,
        ];
    }
}
