<?php

namespace App\Models;

use CodeIgniter\Model;

class EventTeamMemberModel extends Model
{
    protected $table            = 'event_team_members';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'team_id', 'member_type', 'user_id', 'external_id',
        'role', 'dorsal', 'position', 'staff_role', 'created_at',
    ];

    protected $validationRules = [
        'team_id' => 'required|is_natural_no_zero',
        'role'    => 'required|in_list[player,coach,staff]',
    ];
}
