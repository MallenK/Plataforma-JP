<?php

namespace App\Models;

use CodeIgniter\Model;

class ClassSessionModel extends Model
{
    protected $table         = 'class_sessions';
    protected $primaryKey    = 'id';
    protected $useAutoIncrement = true;
    protected $returnType    = 'array';
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'class_id', 'title', 'session_date', 'start_time', 'end_time',
        'location_id', 'location_custom', 'focus',
        'pre_notes', 'post_notes', 'status', 'created_by',
    ];

    // ── Date-range queries ────────────────────────────────────

    public function getForMonth(int $year, int $month): array
    {
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end   = date('Y-m-t', strtotime($start));

        return $this->where('session_date >=', $start)
                    ->where('session_date <=', $end)
                    ->where('status !=', 'cancelled')
                    ->orderBy('session_date', 'ASC')
                    ->orderBy('start_time', 'ASC')
                    ->findAll();
    }

    public function getForWeek(string $weekStart): array
    {
        $end = date('Y-m-d', strtotime($weekStart . ' +6 days'));

        return $this->where('session_date >=', $weekStart)
                    ->where('session_date <=', $end)
                    ->orderBy('session_date', 'ASC')
                    ->orderBy('start_time', 'ASC')
                    ->findAll();
    }

    public function getUpcoming(int $limit = 5): array
    {
        return $this->where('session_date >=', date('Y-m-d'))
                    ->where('status', 'scheduled')
                    ->orderBy('session_date', 'ASC')
                    ->orderBy('start_time', 'ASC')
                    ->findAll($limit);
    }
}
