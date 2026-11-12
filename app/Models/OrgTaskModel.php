<?php

namespace App\Models;

use CodeIgniter\Model;

class OrgTaskModel extends Model
{
    protected $table         = 'org_tasks';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'title', 'description', 'status', 'priority', 'due_date',
        'assigned_to', 'created_by', 'visibility',
        'position', 'completed_at', 'due_notified_at', 'archived_at',
    ];
}
