<?php

namespace App\Models;

use CodeIgniter\Model;

class OrgEventModel extends Model
{
    protected $table         = 'org_events';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'title', 'description', 'start_at', 'end_at', 'all_day', 'location_text',
        'created_by', 'visibility', 'reminder_sent_at', 'archived_at',
    ];
}
