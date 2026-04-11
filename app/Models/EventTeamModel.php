<?php

namespace App\Models;

use CodeIgniter\Model;

class EventTeamModel extends Model
{
    protected $table            = 'event_teams';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps    = false;

    protected $allowedFields = ['event_id', 'name', 'category', 'notes', 'created_at'];

    protected $validationRules = [
        'event_id' => 'required|is_natural_no_zero',
        'name'     => 'required|min_length[2]|max_length[150]',
    ];
}
