<?php

namespace App\Models;

use CodeIgniter\Model;

class ClassSessionPlayerModel extends Model
{
    protected $table         = 'class_session_players';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'session_id', 'user_id', 'coach_id',
        'attendance', 'responded_at',
        'pre_obs', 'post_obs',
        'absence_reason', 'absence_notes',
        'student_note', 'student_noted_at',
        'bono_deducted_at',
    ];
}
