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
        'origin', 'error_ref', 'context',
        'resolved_at', 'closed_at', 'created_at', 'updated_at',
    ];

    public const ORIGINS = ['manual', 'error', 'permiso'];

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

    /**
     * Aplica los filtros comunes (estado, prioridad, categoría, búsqueda) a
     * un query builder de la tabla `tickets t` (con join a `users u`).
     */
    private function applyFilters($builder, array $filters): void
    {
        if (!empty($filters['status'])) {
            $builder->where('t.status', $filters['status']);
        }
        if (!empty($filters['priority'])) {
            $builder->where('t.priority', $filters['priority']);
        }
        if (!empty($filters['category'])) {
            $builder->where('t.category', $filters['category']);
        }
        if (isset($filters['search']) && trim((string) $filters['search']) !== '') {
            $term = trim((string) $filters['search']);
            $builder->groupStart()
                ->like('t.title', $term)
                ->orLike('t.ticket_number', $term)
                ->orLike('t.description', $term)
                ->orLike('u.name', $term)
                ->groupEnd();
        }
    }

    public function getForUser(int $userId, array $filters = [], int $limit = 500, int $offset = 0): array
    {
        $builder = $this->db->table('tickets t')
            ->select('t.*, u.name AS user_name, u.avatar AS user_avatar, u.role AS user_role,
                      (SELECT COUNT(*) FROM ticket_replies tr WHERE tr.ticket_id = t.id) AS reply_count')
            ->join('users u', 'u.id = t.user_id')
            ->where('t.user_id', $userId);

        $this->applyFilters($builder, $filters);

        return $builder->orderBy('t.created_at', 'DESC')
            ->limit($limit, $offset)
            ->get()->getResultArray();
    }

    public function getAll(array $filters = [], int $limit = 30, int $offset = 0): array
    {
        $builder = $this->db->table('tickets t')
            ->select('t.*, u.name AS user_name, u.avatar AS user_avatar, u.role AS user_role,
                      (SELECT COUNT(*) FROM ticket_replies tr WHERE tr.ticket_id = t.id) AS reply_count')
            ->join('users u', 'u.id = t.user_id');

        $this->applyFilters($builder, $filters);

        return $builder->orderBy('t.created_at', 'DESC')
            ->limit($limit, $offset)
            ->get()->getResultArray();
    }

    public function countAll(array $filters = []): int
    {
        $builder = $this->db->table('tickets t')
            ->join('users u', 'u.id = t.user_id');

        $this->applyFilters($builder, $filters);

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

        // Nota: selectAvg() rechaza expresiones con comas y whereNotNull() no
        // existe en el query builder de CI4 — hay que usar select()/where() crudos.
        $avgResolution = $this->db->table('tickets')
            ->select('AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) AS avg_hours', false)
            ->where('status !=', 'abierto')
            ->where('resolved_at IS NOT NULL', null, false)
            ->get()->getRowArray();

        $last30 = $this->db->table('tickets')
            ->select('DATE(created_at) AS day, COUNT(*) AS total', false)
            ->where('created_at >=', date('Y-m-d', strtotime('-30 days')))
            ->groupBy('day')
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
