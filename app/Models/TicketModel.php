<?php

namespace App\Models;

use CodeIgniter\Model;

class TicketModel extends Model
{
    protected $table            = 'tickets';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'ticket_number', 'user_id', 'category', 'priority',
        'title', 'description', 'status',
        'resolved_at', 'closed_at', 'created_at', 'updated_at',
    ];

    // ─────────────────────────────────────────────────────────
    // CONSTANTES
    // ─────────────────────────────────────────────────────────

    public const CATEGORIES = [
        'bug'      => 'Error / Bug',
        'mejora'   => 'Sugerencia / Mejora',
        'consulta' => 'Consulta general',
        'tecnico'  => 'Problema técnico',
        'otro'     => 'Otro',
    ];

    public const PRIORITIES = [
        'baja'    => 'Baja',
        'media'   => 'Media',
        'alta'    => 'Alta',
        'urgente' => 'Urgente',
    ];

    public const STATUSES = [
        'abierto'     => 'Abierto',
        'en_progreso' => 'En progreso',
        'resuelto'    => 'Resuelto',
        'cerrado'     => 'Cerrado',
    ];

    // ─────────────────────────────────────────────────────────
    // CREACIÓN
    // ─────────────────────────────────────────────────────────

    public function createTicket(array $data): int
    {
        $now                  = date('Y-m-d H:i:s');
        $data['ticket_number'] = $this->generateTicketNumber();
        $data['status']        = 'abierto';
        $data['created_at']    = $now;
        $data['updated_at']    = $now;

        $result = $this->insert($data, true);
        return $result === false ? 0 : (int) $result;
    }

    private function generateTicketNumber(): string
    {
        $year    = date('Y');
        $last    = $this->db->table('tickets')
            ->selectMax('id')
            ->get()->getRowArray();
        $next    = (int) ($last['id'] ?? 0) + 1;
        return 'TKT-' . $year . '-' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    // ─────────────────────────────────────────────────────────
    // LISTADOS
    // ─────────────────────────────────────────────────────────

    public function getForUser(int $userId, int $limit = 20, int $offset = 0): array
    {
        return $this->db->table('tickets t')
            ->select('t.*, u.name AS user_name, u.avatar AS user_avatar, u.role AS user_role,
                      (SELECT COUNT(*) FROM ticket_replies tr WHERE tr.ticket_id = t.id) AS reply_count')
            ->join('users u', 'u.id = t.user_id')
            ->where('t.user_id', $userId)
            ->orderBy('t.created_at', 'DESC')
            ->limit($limit, $offset)
            ->get()->getResultArray();
    }

    public function getAll(array $filters = [], int $limit = 30, int $offset = 0): array
    {
        $builder = $this->db->table('tickets t')
            ->select('t.*, u.name AS user_name, u.avatar AS user_avatar, u.role AS user_role,
                      (SELECT COUNT(*) FROM ticket_replies tr WHERE tr.ticket_id = t.id) AS reply_count')
            ->join('users u', 'u.id = t.user_id');

        if (!empty($filters['status'])) {
            $builder->where('t.status', $filters['status']);
        }
        if (!empty($filters['priority'])) {
            $builder->where('t.priority', $filters['priority']);
        }
        if (!empty($filters['category'])) {
            $builder->where('t.category', $filters['category']);
        }
        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $builder->groupStart()
                ->like('t.title', $filters['search'])
                ->orLike('t.ticket_number', $filters['search'])
                ->orLike('u.name', $filters['search'])
                ->groupEnd();
        }

        return $builder->orderBy('t.created_at', 'DESC')
            ->limit($limit, $offset)
            ->get()->getResultArray();
    }

    public function countAll(array $filters = []): int
    {
        $builder = $this->db->table('tickets t')
            ->join('users u', 'u.id = t.user_id');

        if (!empty($filters['status'])) {
            $builder->where('t.status', $filters['status']);
        }
        if (!empty($filters['priority'])) {
            $builder->where('t.priority', $filters['priority']);
        }
        if (!empty($filters['category'])) {
            $builder->where('t.category', $filters['category']);
        }
        if (!empty($filters['search'])) {
            $builder->groupStart()
                ->like('t.title', $filters['search'])
                ->orLike('t.ticket_number', $filters['search'])
                ->orLike('u.name', $filters['search'])
                ->groupEnd();
        }

        return (int) $builder->countAllResults();
    }

    // ─────────────────────────────────────────────────────────
    // DETALLE
    // ─────────────────────────────────────────────────────────

    public function getWithUser(int $id): ?array
    {
        $row = $this->db->table('tickets t')
            ->select('t.*, u.name AS user_name, u.avatar AS user_avatar, u.role AS user_role')
            ->join('users u', 'u.id = t.user_id')
            ->where('t.id', $id)
            ->get()->getRowArray();

        return $row ?: null;
    }

    // ─────────────────────────────────────────────────────────
    // CAMBIO DE ESTADO
    // ─────────────────────────────────────────────────────────

    public function updateStatus(int $id, string $status): void
    {
        $data = ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')];

        if ($status === 'resuelto') {
            $data['resolved_at'] = date('Y-m-d H:i:s');
        }
        if ($status === 'cerrado') {
            $data['closed_at'] = date('Y-m-d H:i:s');
        }

        $this->db->table('tickets')->where('id', $id)->update($data);
    }

    public function updatePriority(int $id, string $priority): void
    {
        $this->db->table('tickets')->where('id', $id)->update([
            'priority'   => $priority,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // ESTADÍSTICAS PARA DASHBOARD
    // ─────────────────────────────────────────────────────────

    public function getStats(): array
    {
        $byStatus = $this->db->table('tickets')
            ->select('status, COUNT(*) AS total')
            ->groupBy('status')
            ->get()->getResultArray();

        $byCategory = $this->db->table('tickets')
            ->select('category, COUNT(*) AS total')
            ->groupBy('category')
            ->get()->getResultArray();

        $byPriority = $this->db->table('tickets')
            ->select('priority, COUNT(*) AS total')
            ->groupBy('priority')
            ->get()->getResultArray();

        $avgResolution = $this->db->table('tickets')
            ->selectAvg('TIMESTAMPDIFF(HOUR, created_at, resolved_at)', 'avg_hours')
            ->where('status !=', 'abierto')
            ->whereNotNull('resolved_at')
            ->get()->getRowArray();

        $last30 = $this->db->table('tickets')
            ->select('DATE(created_at) AS day, COUNT(*) AS total')
            ->where('created_at >=', date('Y-m-d', strtotime('-30 days')))
            ->groupBy('DATE(created_at)')
            ->orderBy('day', 'ASC')
            ->get()->getResultArray();

        return [
            'by_status'      => array_column($byStatus,   'total', 'status'),
            'by_category'    => array_column($byCategory, 'total', 'category'),
            'by_priority'    => array_column($byPriority, 'total', 'priority'),
            'avg_hours'      => round((float) ($avgResolution['avg_hours'] ?? 0), 1),
            'last_30_days'   => $last30,
            'total'          => array_sum(array_column($byStatus, 'total')),
        ];
    }
}
