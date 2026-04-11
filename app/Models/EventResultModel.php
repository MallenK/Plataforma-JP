<?php

namespace App\Models;

use CodeIgniter\Model;

class EventResultModel extends Model
{
    protected $table            = 'event_results';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';

    protected $allowedFields = ['event_id', 'team_id', 'result_text', 'notes'];
}
