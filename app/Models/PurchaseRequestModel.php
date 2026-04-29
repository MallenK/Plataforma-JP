<?php

namespace App\Models;

use CodeIgniter\Model;

class PurchaseRequestModel extends Model
{
    protected $table            = 'purchase_requests';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'name', 'description', 'url', 'price', 'category', 'priority',
        'status', 'admin_comment', 'requested_by', 'reviewed_by',
        'reviewed_at', 'created_at', 'updated_at',
    ];

    public function getAllWithDetails(?string $status = null): array
    {
        $q = $this->db->table('purchase_requests pr')
            ->select('pr.*, u.name AS requester_name, u.avatar AS requester_avatar,
                      rv.name AS reviewer_name')
            ->join('users u', 'u.id = pr.requested_by')
            ->join('users rv', 'rv.id = pr.reviewed_by', 'left');

        if ($status) {
            $q->where('pr.status', $status);
        }

        return $q->orderBy('FIELD(pr.priority, "alta", "media", "baja")', '', false)
                 ->orderBy('pr.created_at', 'DESC')
                 ->get()->getResultArray();
    }

    public function getStats(): array
    {
        $row = $this->db->query("
            SELECT
                COUNT(*) AS total,
                SUM(status = 'pendiente')   AS pendiente,
                SUM(status = 'en_revision') AS en_revision,
                SUM(status = 'aprobado')    AS aprobado,
                SUM(status = 'denegado')    AS denegado,
                SUM(status = 'comprado')    AS comprado,
                SUM(status = 'cancelado')   AS cancelado
            FROM purchase_requests
        ")->getRowArray();

        return $row ?? [
            'total' => 0, 'pendiente' => 0, 'en_revision' => 0,
            'aprobado' => 0, 'denegado' => 0, 'comprado' => 0, 'cancelado' => 0,
        ];
    }
}
