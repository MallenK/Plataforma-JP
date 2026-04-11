<?php

namespace App\Models;

use CodeIgniter\Model;

class ClassModel extends Model
{
    protected $table         = 'classes';
    protected $primaryKey    = 'id';
    protected $useAutoIncrement = true;
    protected $returnType    = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'title', 'description', 'type',
        'recurrence_days', 'recurrence_start', 'recurrence_end',
        'recurrence_time_start', 'recurrence_time_end',
        'default_location_id', 'default_location_custom', 'default_focus',
        'created_by',
    ];
}
